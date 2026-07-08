<?php

namespace App\Http\Controllers;

use App\Models\DeviceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DeviceEventController extends Controller
{
    public function store(Request $request, string $device): Response
    {
        $raw = $request->getContent();
        $eventType = null;
        $status = 'received';
        $errorMessage = null;

        try {
            $eventType = $this->extractEventType($raw);
        } catch (Throwable $e) {
            $status = 'failed';
            $errorMessage = $e->getMessage();

            Log::warning('Hikvision event type extraction failed', [
                'device' => $device,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            DeviceEvent::create([
                'device_code' => $device,
                'source_ip' => $request->ip(),
                'event_type' => $eventType,
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

    private function extractEventType(string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        $trimmed = ltrim($raw);

        if (str_starts_with($trimmed, '<')) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($trimmed);

            if ($xml === false) {
                throw new RuntimeException('Unable to parse XML payload');
            }

            $array = json_decode(json_encode($xml), true);

            return $array['eventType'] ?? null;
        }

        $json = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
            return $json['eventType'] ?? $json['event_type'] ?? null;
        }

        return null;
    }
}
