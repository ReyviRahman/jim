<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class HikvisionWebhookPayloadParser
{
    private const MAX_EVENT_BYTES = 2_000_000;

    private const MAX_REQUEST_BYTES = 15_000_000;

    public function parse(Request $request): ?string
    {
        $requestData = $request->request->all();

        $accessControllerEvent = count($requestData) === 1
            && array_key_exists('AccessControllerEvent', $requestData)
                ? $this->accessControllerEventDocument($requestData['AccessControllerEvent'])
                : null;

        if ($accessControllerEvent !== null) {
            return $accessControllerEvent;
        }

        foreach (['event_log', 'eventLog', 'EventNotificationAlert'] as $key) {
            $payload = $this->structuredDocument($request->request->get($key));

            if ($payload !== null) {
                return $payload;
            }
        }

        if ($requestData !== []) {
            $payload = $this->structuredDocument($requestData);

            if ($payload !== null) {
                return $payload;
            }
        }

        foreach ($requestData as $value) {
            $payload = $this->structuredDocument($value);

            if ($payload !== null) {
                return $payload;
            }
        }

        foreach ($this->uploadedFiles($request->allFiles()) as $file) {
            $payload = $this->uploadedFileDocument($file);

            if ($payload !== null) {
                return $payload;
            }
        }

        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);

        if ($contentLength > self::MAX_REQUEST_BYTES) {
            return null;
        }

        $raw = $request->getContent();

        if (! is_string($raw) || $raw === '' || strlen($raw) > self::MAX_REQUEST_BYTES) {
            return null;
        }

        if (str_contains(strtolower((string) $request->header('Content-Type')), 'multipart/')) {
            return $this->multipartDocument($raw, (string) $request->header('Content-Type'));
        }

        return $this->structuredDocument($raw);
    }

    private function accessControllerEventDocument(mixed $value): ?string
    {
        $payload = $this->structuredDocument($value);

        if ($payload === null || ! str_starts_with(ltrim($payload), '{')) {
            return $payload;
        }

        $event = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($event)) {
            return null;
        }

        $event['eventType'] = 'AccessControllerEvent';

        return $this->structuredDocument($event);
    }

    private function multipartDocument(string $raw, string $contentType): ?string
    {
        if (! preg_match('/boundary\s*=\s*(?:"([^"]+)"|([^;,\s]+))/i', $contentType, $matches)) {
            return null;
        }

        $boundary = ($matches[1] ?? '') !== '' ? $matches[1] : ($matches[2] ?? '');

        if ($boundary === '') {
            return null;
        }

        foreach (explode('--'.$boundary, $raw) as $part) {
            $segments = preg_split('/\r?\n\r?\n/', ltrim($part, "\r\n"), 2);

            if (! is_array($segments) || count($segments) !== 2) {
                continue;
            }

            $payload = $this->structuredDocument(rtrim($segments[1], "\r\n-"));

            if ($payload !== null) {
                return $payload;
            }
        }

        return null;
    }

    private function uploadedFileDocument(UploadedFile $file): ?string
    {
        if (! $file->isValid() || $file->getSize() > self::MAX_EVENT_BYTES) {
            return null;
        }

        $path = $file->getRealPath();

        if ($path === false) {
            return null;
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $this->structuredDocument($contents) : null;
    }

    private function structuredDocument(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value));

        if (! is_string($value)
            || $value === ''
            || $value === '[]'
            || $value === '{}'
            || strlen($value) > self::MAX_EVENT_BYTES) {
            return null;
        }

        return in_array($value[0], ['{', '[', '<'], true) ? $value : null;
    }

    /**
     * @param  array<string, UploadedFile|array<mixed>>  $files
     * @return list<UploadedFile>
     */
    private function uploadedFiles(array $files): array
    {
        $uploadedFiles = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $uploadedFiles[] = $file;
            } elseif (is_array($file)) {
                array_push($uploadedFiles, ...$this->uploadedFiles($file));
            }
        }

        return $uploadedFiles;
    }
}
