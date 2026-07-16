<?php

namespace App;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class HikvisionUserService
{
    /**
     * @throws RequestException
     */
    public function sync(User $user): void
    {
        $baseUrl = rtrim((string) config('services.hikvision.base_url'), '/');
        $endpoint = '/'.ltrim((string) config('services.hikvision.user_endpoint'), '/');

        if ($baseUrl === '') {
            throw new RuntimeException('Hikvision base URL is not configured.');
        }

        Http::acceptJson()
            ->withDigestAuth(
                (string) config('services.hikvision.username'),
                (string) config('services.hikvision.password'),
            )
            ->connectTimeout((int) config('services.hikvision.connect_timeout'))
            ->timeout((int) config('services.hikvision.timeout'))
            ->retry([100, 200], function (Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false)
            ->put($baseUrl.$endpoint, [
                'UserInfo' => [
                    'employeeNo' => (string) $user->id,
                    'name' => $user->name,
                    'userType' => 'normal',
                ],
            ])
            ->throw();
    }
}
