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
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeviceEventController extends Controller
{
    public function __construct(
        private HikvisionAttendanceService $hikvisionAttendanceService,
        private HikvisionWebhookPayloadParser $payloadParser,
    ) {}

    public function store(Request $request): Response
    {
        $device = (string) config('services.hikvision.device_code', 'HQ-BIO-01');
        $payload = $this->payloadParser->parse($request);
        $sourceIp = $request->ip();

        Log::debug('Hikvision webhook hit', [
            'device' => $device,
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->server('CONTENT_LENGTH'),
            'input_keys' => array_keys($request->request->all()),
            'file_keys' => array_keys($request->allFiles()),
            'ip' => $sourceIp,
            'payload_length' => $payload === null ? 0 : strlen($payload),
        ]);

        if ($payload === null) {
            return response('OK', 200);
        }

        try {
            $eventData = $this->extractEventData($payload);
        } catch (Throwable $e) {
            Log::warning('Hikvision event extraction failed', [
                'device' => $device,
                'error' => $e->getMessage(),
            ]);

            return response('OK', 200);
        }

        $rejectionReasons = $this->eventRejectionReasons($eventData);

        if ($rejectionReasons !== []) {
            Log::debug('Hikvision event ignored', [
                'device' => $device,
                'event_type' => $eventData['event_type'],
                'employee_no_present' => $eventData['employee_no'] !== null
                    && $eventData['employee_no'] !== '',
                'attendance_status' => $eventData['attendance_status'],
                'verify_mode' => $eventData['verify_mode'],
                'accessed_at_present' => $eventData['accessed_at'] instanceof Carbon,
                'rejection_reasons' => $rejectionReasons,
            ]);

            return response('OK', 200);
        }

        try {
            DB::transaction(function () use ($device, $eventData, $payload, $sourceIp): void {
                $user = User::query()
                    ->select('id')
                    ->lockForUpdate()
                    ->find($eventData['employee_no']);
                $receivedAt = Carbon::now(config('app.timezone'));
                $eventHash = hash('sha256', implode('|', [
                    $device,
                    $eventData['employee_no'],
                    $payload,
                ]));
                $deviceEventAttributes = [
                    'device_code' => $device,
                    'source_ip' => $sourceIp,
                    'event_type' => $eventData['event_type'],
                    'employee_no' => $eventData['employee_no'],
                    'is_found' => $user !== null,
                    'name' => $eventData['name'],
                    'card_no' => $eventData['card_no'],
                    'door_no' => $eventData['door_no'],
                    'swipe_result' => $eventData['swipe_result'],
                    'attendance_status' => $eventData['attendance_status'],
                    'verify_mode' => $eventData['verify_mode'],
                    'accessed_at' => $eventData['accessed_at'],
                    'payload' => '',
                ];
                $deviceEvent = DeviceEvent::query()->createOrFirst(
                    ['event_hash' => $eventHash],
                    $deviceEventAttributes,
                );

                Log::debug('Hikvision event stored', [
                    'device' => $device,
                    'device_event_id' => $deviceEvent->id,
                    'member_found' => $user !== null,
                    'was_created' => $deviceEvent->wasRecentlyCreated,
                ]);

                if ($deviceEvent->is_found !== ($user !== null)) {
                    $deviceEvent->update(['is_found' => $user !== null]);
                }

                if ($user === null) {
                    return;
                }

                if (! $deviceEvent->wasRecentlyCreated) {
                    return;
                }

                if (! $this->shouldRecordAttendance($eventData)) {
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
            }, attempts: 3);
        } catch (Throwable $e) {
            Log::error('Failed to store Hikvision event', [
                'device' => $device,
                'error' => $e->getMessage(),
            ]);
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
        return $eventData['event_type'] === 'AccessControllerEvent'
            && in_array($eventData['attendance_status'], ['checkIn', 'checkOut'], true)
            && $eventData['verify_mode'] !== 'invalid';
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
