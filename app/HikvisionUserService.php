<?php

namespace App;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HikvisionUserService
{
    /**
     * @throws RequestException
     */
    public function sync(User $user, ?CarbonInterface $validityStart = null, ?CarbonInterface $validityEnd = null): void
    {
        $validityStart ??= now()->startOfYear();
        $validityEnd ??= now()->endOfYear();

        $this->request()
            ->post($this->baseUrl().$this->userEndpoint(), [
                'UserInfo' => [
                    'employeeNo' => (string) $user->id,
                    'name' => $user->name,
                    'userType' => 'normal',
                    'Valid' => [
                        'enable' => true,
                        'beginTime' => $validityStart->format('Y-m-d\\TH:i:s'),
                        'endTime' => $validityEnd->format('Y-m-d\\TH:i:s'),
                    ],
                ],
            ])
            ->throw();
    }

    private function baseUrl(): string
    {
        $baseUrl = rtrim((string) config('services.hikvision.base_url'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Hikvision base URL is not configured.');
        }

        return $baseUrl;
    }

    private function userEndpoint(): string
    {
        return '/'.ltrim((string) config('services.hikvision.user_endpoint'), '/');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withDigestAuth(
                (string) config('services.hikvision.username'),
                (string) config('services.hikvision.password'),
            )
            ->connectTimeout((int) config('services.hikvision.connect_timeout'))
            ->timeout((int) config('services.hikvision.timeout'))
            ->retry([100, 200], function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }
}
