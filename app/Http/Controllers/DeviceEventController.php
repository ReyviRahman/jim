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

        if (empty($raw) || trim($raw) === '') {
            return response('OK', 200);
        }

        $eventData = [
            'event_type' => null,
            'employee_no' => null,
            'name' => null,
            'card_no' => null,
            'door_no' => null,
            'swipe_result' => null,
            'accessed_at' => null,
        ];
        $status = 'received';
        $errorMessage = null;

        try {
            $eventData = $this->extractEventData($raw);
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
                'accessed_at' => $eventData['accessed_at'],
                'payload' => $raw,
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
            'accessed_at' => null,
        ];

        $trimmed = ltrim($raw);

        if (str_starts_with($trimmed, '<')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($trimmed);

            if ($xml === false) {
                throw new RuntimeException('Unable to parse XML payload');
            }

            $array = json_decode(json_encode($xml), true);
            $data['event_type'] = $array['eventType'] ?? null;
            $activePost = $array['ActivePost'] ?? [];

            $data['employee_no'] = $activePost['employeeNoString'] ?? null;
            $data['name'] = $activePost['name'] ?? null;
            $data['card_no'] = $activePost['cardNo'] ?? null;
            $data['door_no'] = $activePost['doorNo'] ?? null;
            $data['swipe_result'] = $activePost['swipeResult'] ?? null;

            if (! empty($array['dateTime'])) {
                $data['accessed_at'] = Carbon::parse($array['dateTime'])->setTimezone(config('app.timezone'));
            }

            return $data;
        }

        $json = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            $data['event_type'] = $json['eventType'] ?? $json['event_type'] ?? null;
            $data['employee_no'] = $json['employeeNoString'] ?? $json['employee_no'] ?? null;
            $data['name'] = $json['name'] ?? null;
            $data['card_no'] = $json['cardNo'] ?? $json['card_no'] ?? null;
            $data['door_no'] = $json['doorNo'] ?? $json['door_no'] ?? null;
            $data['swipe_result'] = $json['swipeResult'] ?? $json['swipe_result'] ?? null;

            if (! empty($json['dateTime'])) {
                $data['accessed_at'] = Carbon::parse($json['dateTime'])->setTimezone(config('app.timezone'));
            }

            return $data;
        }

        return $data;
    }
}
