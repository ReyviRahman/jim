<?php

namespace App\Http\Controllers;

use App\HikvisionAttendanceService;
use App\HikvisionWebhookPayloadParser;
use App\Models\DeviceEvent;
use App\Models\Membership;
use App\Models\MembershipUser;
use App\Models\PtBooking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DeviceEventController extends Controller
{
    private const FAILED_SWIPE_RESULTS = [
        'fail',
        'failed',
        'failure',
        'denied',
        'invalid',
    ];

    public function __construct(
        private HikvisionAttendanceService $hikvisionAttendanceService,
        private HikvisionWebhookPayloadParser $payloadParser,
    ) {}

    public function store(Request $request): Response
    {
        $device = (string) config('services.hikvision.device_code', 'HQ-BIO-01');
        $payload = $this->payloadParser->parse($request);
        $sourceIp = $request->ip();

        if ($payload === null) {
            return response('OK', 200);
        }

        try {
            $eventData = $this->extractEventData($payload);
        } catch (Throwable) {
            return response('OK', 200);
        }

        if ($this->eventRejectionReasons($eventData) !== []) {
            return response('OK', 200);
        }

        $receivedAt = Carbon::now(config('app.timezone'));
        $eventHash = hash('sha256', implode('|', [
            $device,
            $eventData['employee_no'],
            $payload,
        ]));

        try {
            DB::transaction(function () use ($device, $eventData, $eventHash, $receivedAt, $sourceIp): void {
                $user = $this->resolveUser($eventData['employee_no']);
                $deviceEventAttributes = $this->deviceEventAttributes(
                    $device,
                    $sourceIp,
                    $eventData,
                    $user !== null,
                );
                $deviceEvent = DeviceEvent::query()->createOrFirst(
                    ['event_hash' => $eventHash],
                    $deviceEventAttributes,
                );
                $wasRecentlyCreated = $deviceEvent->wasRecentlyCreated;
                $deviceEvent = DeviceEvent::query()
                    ->whereKey($deviceEvent->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($deviceEvent->is_found !== ($user !== null)) {
                    $deviceEvent->update(['is_found' => $user !== null]);
                }

                if (! $wasRecentlyCreated && $deviceEvent->status !== 'failed') {
                    return;
                }

                if ($user === null || ! $this->shouldRecordAttendance($eventData)) {
                    $this->markDeviceEventAsProcessed($deviceEvent);

                    return;
                }

                $dailyAttendanceWasCreated = $this->hikvisionAttendanceService->record(
                    $user,
                    $deviceEvent,
                    $receivedAt,
                );

                if ($dailyAttendanceWasCreated) {
                    $this->markTodaysPtBookingAsAttended($user, $receivedAt);
                }

                $this->markDeviceEventAsProcessed($deviceEvent);
            }, attempts: 3);
        } catch (Throwable) {
            $this->storeFailedDeviceEvent($device, $sourceIp, $eventData, $eventHash);
        }

        return response('OK', 200);
    }

    private function extractEventData(string $raw): array
    {
        $data = [
            'event_type' => null,
            'employee_no' => null,
            'name' => null,
            'card_no' => null,
            'door_no' => null,
            'swipe_result' => null,
            'attendance_status' => null,
            'verify_mode' => null,
            'accessed_at' => null,
        ];

        $trimmed = ltrim($raw);
        $array = $this->decodePayload($trimmed);

        if (! is_array($array)) {
            return $data;
        }

        // Hikvision multipart form sends the JSON inside an "event_log" field.
        if (isset($array['event_log']) && is_string($array['event_log'])) {
            $decoded = json_decode($array['event_log'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $array = $decoded;
            }
        }

        $event = $array;

        // Hikvision nests the actual event under the eventType key,
        // e.g. AccessControllerEvent -> { ... }.
        if (isset($event['eventType']) && is_array($event[$event['eventType']] ?? null)) {
            $event = array_merge($event, $event[$event['eventType']]);
        }

        // Older XML format uses ActivePost as the nested container.
        if (isset($event['ActivePost']) && is_array($event['ActivePost'])) {
            $event = array_merge($event, $event['ActivePost']);
        }

        $data['event_type'] = $event['eventType'] ?? $event['event_type'] ?? null;
        $employeeNo = $event['employeeNoString']
            ?? $event['employeeNo']
            ?? $event['employeeId']
            ?? $event['employeeID']
            ?? $event['employee_id']
            ?? $event['employee_no']
            ?? $event['Employee ID']
            ?? null;
        $data['employee_no'] = is_scalar($employeeNo) ? trim((string) $employeeNo) : null;
        $data['name'] = $event['name'] ?? null;
        $data['card_no'] = $event['cardNo'] ?? $event['card_no'] ?? null;
        $data['door_no'] = $event['doorNo'] ?? $event['door_no'] ?? null;
        $data['swipe_result'] = $event['swipeResult'] ?? $event['swipe_result'] ?? null;
        $data['attendance_status'] = $event['attendanceStatus'] ?? $event['attendance_status'] ?? null;
        $data['verify_mode'] = $event['currentVerifyMode'] ?? $event['verify_mode'] ?? null;

        $dateTime = $event['dateTime'] ?? $event['date_time'] ?? null;
        if (! empty($dateTime)) {
            $data['accessed_at'] = Carbon::parse($dateTime)->setTimezone(config('app.timezone'));
        }

        return $data;
    }

    private function decodePayload(string $trimmed): ?array
    {
        if (str_starts_with($trimmed, '<')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($trimmed);

            if ($xml === false) {
                throw new RuntimeException('Unable to parse XML payload');
            }

            return json_decode(json_encode($xml), true);
        }

        $json = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return list<string>
     */
    private function eventRejectionReasons(array $eventData): array
    {
        $reasons = [];

        if ($eventData['employee_no'] === null || $eventData['employee_no'] === '') {
            $reasons[] = 'missing_employee_no';
        }

        return $reasons;
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function shouldRecordAttendance(array $eventData): bool
    {
        $verifyMode = $this->normalizeEventValue($eventData['verify_mode']);
        $swipeResult = $this->normalizeEventValue($eventData['swipe_result']);

        return $verifyMode !== 'invalid'
            && ! in_array($swipeResult, self::FAILED_SWIPE_RESULTS, true);
    }

    private function resolveUser(string $employeeNumber): ?User
    {
        $explicitlyMappedUser = User::query()
            ->select(['id', 'hikvision_employee_no'])
            ->where('hikvision_employee_no', $employeeNumber)
            ->lockForUpdate()
            ->first();

        if ($explicitlyMappedUser !== null) {
            return $explicitlyMappedUser;
        }

        if (! ctype_digit($employeeNumber)) {
            return null;
        }

        return User::query()
            ->select(['id', 'hikvision_employee_no'])
            ->whereKey($employeeNumber)
            ->whereNull('hikvision_employee_no')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $eventData
     * @return array<string, mixed>
     */
    private function deviceEventAttributes(
        string $device,
        ?string $sourceIp,
        array $eventData,
        bool $isFound,
    ): array {
        return [
            'device_code' => $device,
            'source_ip' => $sourceIp,
            'event_type' => $eventData['event_type'],
            'employee_no' => $eventData['employee_no'],
            'is_found' => $isFound,
            'name' => $eventData['name'],
            'card_no' => $eventData['card_no'],
            'door_no' => $eventData['door_no'],
            'swipe_result' => $eventData['swipe_result'],
            'attendance_status' => $eventData['attendance_status'],
            'verify_mode' => $eventData['verify_mode'],
            'accessed_at' => $eventData['accessed_at'],
            'payload' => '',
            'status' => 'received',
            'error_message' => null,
        ];
    }

    private function markDeviceEventAsProcessed(DeviceEvent $deviceEvent): void
    {
        if ($deviceEvent->status === 'received' && $deviceEvent->error_message === null) {
            return;
        }

        $deviceEvent->update([
            'status' => 'received',
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function storeFailedDeviceEvent(
        string $device,
        ?string $sourceIp,
        array $eventData,
        string $eventHash,
    ): void {
        try {
            DB::transaction(function () use ($device, $sourceIp, $eventData, $eventHash): void {
                $user = $this->resolveUser($eventData['employee_no']);
                $deviceEvent = DeviceEvent::query()
                    ->where('event_hash', $eventHash)
                    ->lockForUpdate()
                    ->first();

                if ($deviceEvent?->status === 'received') {
                    return;
                }

                $attributes = [
                    ...$this->deviceEventAttributes($device, $sourceIp, $eventData, $user !== null),
                    'status' => 'failed',
                    'error_message' => 'Attendance processing failed.',
                ];

                if ($deviceEvent === null) {
                    DeviceEvent::query()->create([
                        'event_hash' => $eventHash,
                        ...$attributes,
                    ]);

                    return;
                }

                $deviceEvent->update($attributes);
            }, attempts: 3);
        } catch (Throwable) {
        }
    }

    private function normalizeEventValue(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return strtolower(trim((string) $value));
    }

    private function markTodaysPtBookingAsAttended(User $user, Carbon $receivedAt): void
    {
        $bookings = PtBooking::query()
            ->whereIn('membership_id', MembershipUser::query()
                ->select('membership_id')
                ->where('user_id', $user->id))
            ->where('booking_date', $receivedAt->toDateString())
            ->where('attendance', 'not_yet')
            ->whereIn('status', ['pending', 'approved'])
            ->orderBy('booking_time')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $booking = $bookings->sort(function (PtBooking $leftBooking, PtBooking $rightBooking) use ($receivedAt): int {
            $distanceComparison = $this->bookingDistanceInSeconds($leftBooking, $receivedAt)
                <=> $this->bookingDistanceInSeconds($rightBooking, $receivedAt);

            if ($distanceComparison !== 0) {
                return $distanceComparison;
            }

            $timeComparison = strcmp(
                (string) $leftBooking->getRawOriginal('booking_time'),
                (string) $rightBooking->getRawOriginal('booking_time')
            );

            return $timeComparison !== 0
                ? $timeComparison
                : $leftBooking->id <=> $rightBooking->id;
        })->first();

        if ($booking === null) {
            return;
        }

        $booking->update([
            'attendance' => 'attended',
            'status' => $booking->status === 'pending' ? 'approved' : $booking->status,
        ]);

        if ($booking->is_free) {
            return;
        }

        $membership = Membership::query()
            ->whereKey($booking->membership_id)
            ->lockForUpdate()
            ->first();

        if ($membership === null || $membership->remaining_sessions === null || $membership->remaining_sessions <= 0) {
            return;
        }

        $remainingSessions = $membership->remaining_sessions - 1;

        $membership->update([
            'remaining_sessions' => $remainingSessions,
            'status' => $remainingSessions === 0 ? 'completed' : $membership->status,
        ]);
    }

    private function bookingDistanceInSeconds(PtBooking $booking, Carbon $receivedAt): int
    {
        [$hours, $minutes, $seconds] = array_map(
            'intval',
            explode(':', (string) $booking->getRawOriginal('booking_time'))
        );
        $bookingTimeInSeconds = ($hours * 3600) + ($minutes * 60) + $seconds;
        $receivedTimeInSeconds = ($receivedAt->hour * 3600) + ($receivedAt->minute * 60) + $receivedAt->second;

        return abs($bookingTimeInSeconds - $receivedTimeInSeconds);
    }
}
