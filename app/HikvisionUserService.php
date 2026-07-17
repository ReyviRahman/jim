<?php

namespace App;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class HikvisionUserService
{
    /**
     * @param  array<int, int|string>  $employeeNumbers
     * @return array<int, string>
     *
     * @throws RequestException
     */
    public function existingEmployeeNumbers(array $employeeNumbers): array
    {
        $employeeNumbers = collect($employeeNumbers)
            ->map(fn (int|string $employeeNumber): string => (string) $employeeNumber)
            ->filter()
            ->unique()
            ->values();

        if ($employeeNumbers->isEmpty()) {
            return [];
        }

        $this->ensureDeviceIsAvailable();

        try {
            $response = $this->request()
                ->post($this->baseUrl().$this->userSearchEndpoint(), [
                    'UserInfoSearchCond' => [
                        'searchID' => (string) Str::uuid(),
                        'searchResultPosition' => 0,
                        'maxResults' => $employeeNumbers->count(),
                        'EmployeeNoList' => $employeeNumbers
                            ->map(fn (string $employeeNumber): array => ['employeeNo' => $employeeNumber])
                            ->all(),
                    ],
                ])
                ->throw();

            $payload = $response->json();

            if (isset($payload['ResponseStatus'])) {
                throw new RuntimeException('Hikvision member search returned an error response.');
            }

            $searchResult = data_get($payload, 'UserInfoSearch');

            if (! is_array($searchResult)) {
                throw new RuntimeException('Hikvision member search returned an invalid response.');
            }

            $responseStatus = data_get($searchResult, 'responseStatusStrg');

            if (is_string($responseStatus) && ! in_array(strtoupper($responseStatus), ['OK', 'MORE', 'NO MATCH', 'NO MATCHES'], true)) {
                throw new RuntimeException('Hikvision member search did not complete successfully.');
            }

            $matchList = data_get($searchResult, 'UserInfo', data_get($searchResult, 'MatchList', []));

            if (isset($matchList['employeeNo']) || isset($matchList['UserInfo'])) {
                $matchList = [$matchList];
            }

            $this->clearDeviceUnavailable();

            return collect($matchList)
                ->map(function (mixed $match): ?string {
                    if (! is_array($match)) {
                        return null;
                    }

                    $employeeNumber = data_get($match, 'employeeNo') ?? data_get($match, 'UserInfo.employeeNo');

                    return is_scalar($employeeNumber) ? (string) $employeeNumber : null;
                })
                ->filter()
                ->intersect($employeeNumbers)
                ->unique()
                ->values()
                ->all();
        } catch (ConnectionException $exception) {
            $this->markDeviceUnavailable();

            throw $exception;
        }
    }

    /**
     * @throws RequestException
     */
    public function sync(User $user, ?CarbonInterface $validityStart = null, ?CarbonInterface $validityEnd = null): void
    {
        $validityStart ??= now()->startOfYear();
        $validityEnd ??= now()->endOfYear();

        $this->ensureDeviceIsAvailable();

        try {
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

            $this->clearDeviceUnavailable();
        } catch (ConnectionException $exception) {
            $this->markDeviceUnavailable();

            throw $exception;
        }
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

    private function userSearchEndpoint(): string
    {
        return '/'.ltrim((string) config('services.hikvision.user_search_endpoint', '/ISAPI/AccessControl/UserInfo/Search?format=json'), '/');
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->withDigestAuth(
                (string) config('services.hikvision.username'),
                (string) config('services.hikvision.password'),
            )
            ->connectTimeout((int) config('services.hikvision.connect_timeout'))
            ->timeout((int) config('services.hikvision.timeout'));
    }

    private function ensureDeviceIsAvailable(): void
    {
        if (Cache::has($this->deviceUnavailableCacheKey())) {
            throw new RuntimeException('Hikvision device is temporarily unavailable.');
        }
    }

    private function markDeviceUnavailable(): void
    {
        Cache::put(
            $this->deviceUnavailableCacheKey(),
            true,
            now()->addSeconds((int) config('services.hikvision.failure_cooldown', 60)),
        );
    }

    private function clearDeviceUnavailable(): void
    {
        Cache::forget($this->deviceUnavailableCacheKey());
    }

    private function deviceUnavailableCacheKey(): string
    {
        return 'hikvision:unavailable:'.md5($this->baseUrl());
    }
}
