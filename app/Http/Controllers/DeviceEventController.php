<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
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
    public function store(Request $request): Response
    {
        $receivedAt = Carbon::now(config('app.timezone'));
        $device = (string) config('services.hikvision.device_code', 'HQ-BIO-01');
        $payload = $this->payload($request);

        Log::debug('Hikvision webhook hit', [
            'device' => $device,
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
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

        if (! $this->shouldStoreEvent($eventData)) {
            return response('OK', 200);
        }

        try {
            DB::transaction(function () use ($device, $eventData, $receivedAt): void {
                $user = User::query()
                    ->select('id')
                    ->find($eventData['employee_no']);
                $eventHash = hash('sha256', implode('|', [
                    $device,
                    $eventData['employee_no'],
                    $eventData['accessed_at']->toIso8601String(),
                    $eventData['attendance_status'],
                ]));

                $deviceEvent = DeviceEvent::firstOrCreate(['event_hash' => $eventHash], [
                    'device_code' => $device,
                    'employee_no' => $eventData['employee_no'],
                    'is_found' => $user !== null,
                    'name' => $eventData['name'],
                    'attendance_status' => $eventData['attendance_status'],
                    'verify_mode' => $eventData['verify_mode'],
                    'accessed_at' => $eventData['accessed_at'],
                    'payload' => '',
                ]);

                if ($deviceEvent->is_found !== ($user !== null)) {
                    $deviceEvent->update(['is_found' => $user !== null]);
                }

                if ($user === null) {
                    return;
                }

                $attendance = Attendance::firstOrCreate(['device_event_id' => $deviceEvent->id], [
                    'user_id' => $user->id,
                    'membership_id' => null,
                    'type' => null,
                    'attendance_status' => $eventData['attendance_status'],
                    'check_in_time' => $eventData['accessed_at'],
                ]);

                if ($attendance->wasRecentlyCreated) {
                    $this->markTodaysPtBookingAsAttended($user, $receivedAt);
                }
            });
        } catch (Throwable $e) {
            Log::error('Failed to store Hikvision event', [
                'device' => $device,
                'error' => $e->getMessage(),
            ]);
        }

        return response('OK', 200);
    }

    private function payload(Request $request): ?string
    {
        $eventLog = $request->input('event_log');

        if (is_string($eventLog) && trim($eventLog) !== '') {
            return $eventLog;
        }

        $raw = trim($request->getContent());

        if ($raw === '' || $raw === '[]' || $raw === '{}') {
            return null;
        }

        return $raw;
    }

    private function extractEventData(string $raw): array
    {
        $data = [
            'event_type' => null,
            'employee_no' => null,
            'name' => null,
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
        $data['employee_no'] = $event['employeeNoString'] ?? $event['employee_no'] ?? null;
        $data['name'] = $event['name'] ?? null;
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

    private function shouldStoreEvent(array $eventData): bool
    {
        return $eventData['event_type'] === 'AccessControllerEvent'
            && ! empty($eventData['employee_no'])
            && in_array($eventData['attendance_status'], ['checkIn', 'checkOut'], true)
            && $eventData['verify_mode'] !== 'invalid'
            && $eventData['accessed_at'] instanceof Carbon;
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
