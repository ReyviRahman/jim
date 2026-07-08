<?php

namespace App\Http\Controllers;

use App\Models\DeviceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeviceEventController extends Controller
{
    public function store(Request $request, string $device): Response
    {
        $raw = $request->getContent();
        $formData = $request->except(array_keys($request->allFiles()));

        Log::debug('Hikvision webhook hit', [
            'device' => $device,
            'method' => $request->method(),
            'url' => $request->url(),
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
            'payload_length' => strlen($raw),
            'payload_preview' => substr($raw, 0, 500),
            'form_data' => $formData,
        ]);

        $payload = $raw;

        if (empty($payload) || trim($payload) === '') {
            $payload = json_encode($formData);
        }

        if (empty($payload) || trim($payload) === '' || $payload === '[]' || $payload === '{}') {
            return response('OK', 200);
        }

        $eventData = [
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
        $status = 'received';
        $errorMessage = null;

        try {
            $eventData = $this->extractEventData($payload);
        } catch (Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();

            Log::warning('Hikvision event extraction failed', [
                'device' => $device,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            DeviceEvent::create([
                'device_code' => $device,
                'source_ip' => $request->ip(),
                'event_type' => $eventData['event_type'],
                'employee_no' => $eventData['employee_no'],
                'name' => $eventData['name'],
                'card_no' => $eventData['card_no'],
                'door_no' => $eventData['door_no'],
                'swipe_result' => $eventData['swipe_result'],
                'attendance_status' => $eventData['attendance_status'],
                'verify_mode' => $eventData['verify_mode'],
                'accessed_at' => $eventData['accessed_at'],
                'payload' => $payload,
                'status' => $status,
                'error_message' => $errorMessage,
            ]);
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
        $data['employee_no'] = $event['employeeNoString'] ?? $event['employee_no'] ?? null;
        $data['name'] = $event['name'] ?? null;
        $data['card_no'] = $event['cardNo'] ?? $event['card_no'] ?? null;
        $data['door_no'] = $event['doorNo'] ?? $event['door_no'] ?? null;
        $data['attendance_status'] = $event['attendanceStatus'] ?? $event['attendance_status'] ?? null;
        $data['verify_mode'] = $event['currentVerifyMode'] ?? $event['verify_mode'] ?? null;

        $data['swipe_result'] = $this->determineSwipeResult($event);

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

    private function determineSwipeResult(array $event): ?string
    {
        if (isset($event['swipeResult'])) {
            return $event['swipeResult'];
        }

        $verifyMode = $event['currentVerifyMode'] ?? null;
        if ($verifyMode === 'invalid') {
            return 'failed';
        }

        $attendanceStatus = $event['attendanceStatus'] ?? null;
        if (in_array($attendanceStatus, ['checkIn', 'checkOut'], true)) {
            return 'success';
        }

        return null;
    }
}
