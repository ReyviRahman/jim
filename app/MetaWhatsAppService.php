<?php

namespace App;

use App\Exceptions\MetaWhatsAppException;
use App\Models\MembershipTransaction;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MetaWhatsAppService
{
    /**
     * @throws MetaWhatsAppException
     */
    public function completeEmbeddedSignup(
        string $authorizationCode,
        string $wabaId,
        ?string $phoneNumberId,
        string $twoStepPin,
        User $connectedBy,
    ): WhatsAppIntegration {
        $this->ensureOnboardingConfigurationIsComplete();
        $this->ensureMetaIdIsValid($wabaId, 'WABA ID');

        if ($phoneNumberId !== null) {
            $this->ensureMetaIdIsValid($phoneNumberId, 'Phone Number ID');
        }

        try {
            $tokenPayload = $this->exchangeAuthorizationCode($authorizationCode);
            $accessToken = data_get($tokenPayload, 'access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new MetaWhatsAppException('Meta tidak mengembalikan access token yang valid.');
            }

            $this->validateAccessToken($accessToken);
            $this->validateWaba($accessToken, $wabaId);

            $phoneNumber = $this->resolvePhoneNumber($accessToken, $wabaId, $phoneNumberId);
            $this->registerPhoneNumber($accessToken, (string) $phoneNumber['id'], $twoStepPin);

            $expiresIn = filter_var(data_get($tokenPayload, 'expires_in'), FILTER_VALIDATE_INT);

            return DB::transaction(function () use (
                $accessToken,
                $connectedBy,
                $expiresIn,
                $phoneNumber,
                $wabaId,
            ): WhatsAppIntegration {
                return WhatsAppIntegration::query()->updateOrCreate(
                    ['provider' => WhatsAppIntegration::PROVIDER],
                    [
                        'waba_id' => $wabaId,
                        'phone_number_id' => (string) $phoneNumber['id'],
                        'display_phone_number' => data_get($phoneNumber, 'display_phone_number'),
                        'verified_name' => data_get($phoneNumber, 'verified_name'),
                        'access_token' => $accessToken,
                        'token_expires_at' => is_int($expiresIn) && $expiresIn > 0
                            ? now()->addSeconds($expiresIn)
                            : null,
                        'status' => WhatsAppIntegration::STATUS_CONNECTED,
                        'connected_by_user_id' => $connectedBy->id,
                        'connected_at' => now(),
                        'last_verified_at' => now(),
                    ],
                );
            });
        } catch (ConnectionException|RequestException $exception) {
            throw new MetaWhatsAppException($this->safeRequestFailureMessage($exception, 'Proses koneksi WhatsApp ke Meta gagal.'));
        }
    }

    /**
     * @throws MetaWhatsAppException
     */
    public function verifyConnection(WhatsAppIntegration $integration): WhatsAppIntegration
    {
        if (! $integration->isConnected()) {
            throw new MetaWhatsAppException('Integrasi WhatsApp belum terhubung atau token sudah kedaluwarsa.');
        }

        try {
            $response = $this->request($integration->access_token)
                ->get($this->graphUrl('/'.$integration->phone_number_id), [
                    'fields' => 'id,display_phone_number,verified_name',
                ])
                ->throw();

            $phoneNumber = $response->json();

            if ((string) data_get($phoneNumber, 'id') !== $integration->phone_number_id) {
                throw new MetaWhatsAppException('Phone Number ID dari Meta tidak sesuai dengan integrasi tersimpan.');
            }

            $integration->update([
                'display_phone_number' => data_get($phoneNumber, 'display_phone_number', $integration->display_phone_number),
                'verified_name' => data_get($phoneNumber, 'verified_name', $integration->verified_name),
                'status' => WhatsAppIntegration::STATUS_CONNECTED,
                'last_verified_at' => now(),
            ]);

            return $integration->refresh();
        } catch (ConnectionException|RequestException $exception) {
            $this->markForReconnectWhenTokenIsInvalid($integration, $exception);

            throw new MetaWhatsAppException($this->safeRequestFailureMessage($exception, 'Pemeriksaan koneksi WhatsApp gagal.'));
        }
    }

    /**
     * @throws MetaWhatsAppException
     */
    public function sendTransaction(MembershipTransaction $transaction): string
    {
        $this->ensureMessagingConfigurationIsComplete();

        $integration = WhatsAppIntegration::current();

        if ($integration === null || ! $integration->isConnected()) {
            throw new MetaWhatsAppException('WhatsApp belum terhubung. Hubungkan ulang melalui Pengaturan WhatsApp.');
        }

        $transaction->loadMissing([
            'user',
            'admin',
            'followUp',
            'followUpTwo',
            'membership.members',
        ]);

        try {
            $response = $this->request($integration->access_token)
                ->post($this->graphUrl('/'.$integration->phone_number_id.'/messages'), [
                    'messaging_product' => 'whatsapp',
                    'to' => $this->recipient(),
                    'type' => 'template',
                    'template' => [
                        'name' => (string) config('services.meta_whatsapp.template_name'),
                        'language' => [
                            'code' => (string) config('services.meta_whatsapp.template_language'),
                        ],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => array_map(
                                static fn (string $value): array => [
                                    'type' => 'text',
                                    'text' => $value,
                                ],
                                $this->transactionTemplateParameters($transaction),
                            ),
                        ]],
                    ],
                ])
                ->throw();

            $messageId = data_get($response->json(), 'messages.0.id');

            if (! is_string($messageId) || $messageId === '') {
                throw new MetaWhatsAppException('Meta menerima request tanpa mengembalikan WhatsApp Message ID.');
            }

            return $messageId;
        } catch (ConnectionException|RequestException $exception) {
            $this->markForReconnectWhenTokenIsInvalid($integration, $exception);

            throw new MetaWhatsAppException($this->safeRequestFailureMessage($exception, 'Pengiriman transaksi melalui WhatsApp gagal.'));
        }
    }

    /**
     * @return array<int, string>
     */
    public function transactionTemplateParameters(MembershipTransaction $transaction): array
    {
        $memberNames = $transaction->membership?->members
            ?->pluck('name')
            ->filter()
            ->values()
            ->implode(', ');

        $memberNames = filled($memberNames)
            ? $memberNames
            : ($transaction->user?->name ?? 'User Terhapus');

        $isOtherIncome = $transaction->transaction_type === 'Pemasukan Lain';

        return collect([
            $transaction->invoice_number,
            $memberNames,
            $transaction->payment_date?->format('d M Y') ?? '-',
            $isOtherIncome ? '-' : ($transaction->start_date?->format('d M Y') ?? 'BELUM AKTIF'),
            $isOtherIncome ? '-' : ($transaction->end_date?->format('d M Y') ?? 'BELUM AKTIF'),
            $transaction->transaction_type,
            $transaction->package_name,
            $transaction->notes,
            'Rp '.number_format((float) $transaction->amount, 0, ',', '.'),
            strtoupper((string) $transaction->payment_method),
            $transaction->admin?->name,
            $transaction->followUp?->name,
            $transaction->followUpTwo?->name,
        ])->map(fn (mixed $value): string => $this->normalizeTemplateValue($value))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeAuthorizationCode(string $authorizationCode): array
    {
        return $this->request()
            ->asForm()
            ->post($this->graphUrl('/oauth/access_token'), [
                'client_id' => config('services.meta_whatsapp.app_id'),
                'client_secret' => config('services.meta_whatsapp.app_secret'),
                'code' => $authorizationCode,
            ])
            ->throw()
            ->json();
    }

    private function validateAccessToken(string $accessToken): void
    {
        $appAccessToken = config('services.meta_whatsapp.app_id').'|'.config('services.meta_whatsapp.app_secret');
        $tokenData = $this->request($appAccessToken)
            ->get($this->graphUrl('/debug_token'), ['input_token' => $accessToken])
            ->throw()
            ->json('data');

        if (! data_get($tokenData, 'is_valid') || (string) data_get($tokenData, 'app_id') !== (string) config('services.meta_whatsapp.app_id')) {
            throw new MetaWhatsAppException('Access token Meta tidak valid untuk aplikasi ini.');
        }

        $scopes = collect(data_get($tokenData, 'scopes', []));
        $requiredScopes = collect([
            'business_management',
            'whatsapp_business_management',
            'whatsapp_business_messaging',
        ]);

        if ($requiredScopes->diff($scopes)->isNotEmpty()) {
            throw new MetaWhatsAppException('Izin WhatsApp Business pada access token belum lengkap.');
        }
    }

    private function validateWaba(string $accessToken, string $wabaId): void
    {
        $resolvedWabaId = $this->request($accessToken)
            ->get($this->graphUrl('/'.$wabaId), ['fields' => 'id,name'])
            ->throw()
            ->json('id');

        if ((string) $resolvedWabaId !== $wabaId) {
            throw new MetaWhatsAppException('WABA yang dipilih tidak dapat diverifikasi.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePhoneNumber(string $accessToken, string $wabaId, ?string $phoneNumberId): array
    {
        $phoneNumbers = collect(
            $this->request($accessToken)
                ->get($this->graphUrl('/'.$wabaId.'/phone_numbers'), [
                    'fields' => 'id,display_phone_number,verified_name',
                ])
                ->throw()
                ->json('data', []),
        );

        if ($phoneNumberId !== null) {
            $phoneNumber = $phoneNumbers->first(
                static fn (array $phone): bool => (string) data_get($phone, 'id') === $phoneNumberId,
            );
        } elseif ($phoneNumbers->count() === 1) {
            $phoneNumber = $phoneNumbers->first();
        } else {
            throw new MetaWhatsAppException('Meta tidak mengembalikan satu nomor WhatsApp yang dapat dipilih.');
        }

        if (! is_array($phoneNumber) || blank(data_get($phoneNumber, 'id'))) {
            throw new MetaWhatsAppException('Nomor WhatsApp tidak ditemukan pada WABA yang dipilih.');
        }

        return $phoneNumber;
    }

    private function registerPhoneNumber(string $accessToken, string $phoneNumberId, string $twoStepPin): void
    {
        $this->request($accessToken)
            ->post($this->graphUrl('/'.$phoneNumberId.'/register'), [
                'messaging_product' => 'whatsapp',
                'pin' => $twoStepPin,
            ])
            ->throw();
    }

    private function request(?string $accessToken = null): PendingRequest
    {
        $request = Http::acceptJson()
            ->connectTimeout((int) config('services.meta_whatsapp.connect_timeout', 5))
            ->timeout((int) config('services.meta_whatsapp.timeout', 15));

        return filled($accessToken) ? $request->withToken($accessToken) : $request;
    }

    private function graphUrl(string $path): string
    {
        return rtrim((string) config('services.meta_whatsapp.graph_base_url'), '/')
            .'/'.trim((string) config('services.meta_whatsapp.graph_version'), '/')
            .'/'.ltrim($path, '/');
    }

    private function recipient(): string
    {
        return preg_replace('/\D+/', '', (string) config('services.meta_whatsapp.recipient')) ?? '';
    }

    private function normalizeTemplateValue(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        return Str::of((string) $value)->squish()->limit(1024, '')->toString();
    }

    private function ensureMetaIdIsValid(string $id, string $label): void
    {
        if (preg_match('/^\d+$/', $id) !== 1) {
            throw new MetaWhatsAppException($label.' dari Meta tidak valid.');
        }
    }

    private function ensureOnboardingConfigurationIsComplete(): void
    {
        $requiredValues = [
            config('services.meta_whatsapp.app_id'),
            config('services.meta_whatsapp.app_secret'),
            config('services.meta_whatsapp.login_config_id'),
            config('services.meta_whatsapp.graph_version'),
            config('services.meta_whatsapp.graph_base_url'),
        ];

        if (collect($requiredValues)->contains(fn (mixed $value): bool => blank($value))) {
            throw new MetaWhatsAppException('Konfigurasi Meta WhatsApp pada server belum lengkap.');
        }
    }

    private function ensureMessagingConfigurationIsComplete(): void
    {
        $requiredValues = [
            config('services.meta_whatsapp.graph_version'),
            config('services.meta_whatsapp.graph_base_url'),
            $this->recipient(),
            config('services.meta_whatsapp.template_name'),
            config('services.meta_whatsapp.template_language'),
        ];

        if (collect($requiredValues)->contains(fn (mixed $value): bool => blank($value))) {
            throw new MetaWhatsAppException('Konfigurasi pengiriman WhatsApp pada server belum lengkap.');
        }
    }

    private function markForReconnectWhenTokenIsInvalid(
        WhatsAppIntegration $integration,
        ConnectionException|RequestException $exception,
    ): void {
        if (! $exception instanceof RequestException) {
            return;
        }

        if ((int) data_get($exception->response->json(), 'error.code') === 190) {
            $integration->update(['status' => WhatsAppIntegration::STATUS_NEEDS_RECONNECT]);
        }
    }

    private function safeRequestFailureMessage(
        ConnectionException|RequestException $exception,
        string $fallback,
    ): string {
        if ($exception instanceof ConnectionException) {
            return 'Tidak dapat terhubung ke layanan Meta. Coba beberapa saat lagi.';
        }

        $errorCode = data_get($exception->response->json(), 'error.code');

        return filled($errorCode) ? $fallback.' Kode Meta: '.$errorCode.'.' : $fallback;
    }
}
