<?php

namespace App\Http\Controllers;

use App\Models\DeviceEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeviceEventController extends Controller
{
    public function store(Request $request): Response
    {
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
            $isFound = $eventData['employee_no'] !== null
                && User::query()->whereKey($eventData['employee_no'])->exists();
            $eventHash = hash('sha256', implode('|', [
                $device,
                $eventData['employee_no'],
                $eventData['accessed_at']->toIso8601String(),
                $eventData['attendance_status'],
            ]));

            DeviceEvent::firstOrCreate(['event_hash' => $eventHash], [
                'device_code' => $device,
                'employee_no' => $eventData['employee_no'],
                'is_found' => $isFound,
                'name' => $eventData['name'],
                'attendance_status' => $eventData['attendance_status'],
                'verify_mode' => $eventData['verify_mode'],
                'accessed_at' => $eventData['accessed_at'],
                'payload' => '',
            ]);
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
}
