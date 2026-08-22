<?php

use App\Exceptions\MetaWhatsAppException;
use App\MetaWhatsAppService;
use App\Models\WhatsAppIntegration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::admin')] class extends Component
{
    public string $twoStepPin = '';

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    #[Computed]
    public function integration(): ?WhatsAppIntegration
    {
        return WhatsAppIntegration::current();
    }

    #[Computed]
    public function isConfigured(): bool
    {
        return collect([
            config('services.meta_whatsapp.app_id'),
            config('services.meta_whatsapp.app_secret'),
            config('services.meta_whatsapp.login_config_id'),
            config('services.meta_whatsapp.graph_version'),
        ])->every(fn (mixed $value): bool => filled($value));
    }

    public function completeWhatsAppSignup(
        string $authorizationCode,
        string $wabaId,
        ?string $phoneNumberId,
        MetaWhatsAppService $metaWhatsAppService,
    ): void {
        $this->authorizeAdmin();

        $this->validate([
            'twoStepPin' => ['required', 'digits:6'],
        ], [
            'twoStepPin.required' => 'PIN two-step verification wajib diisi.',
            'twoStepPin.digits' => 'PIN harus terdiri dari tepat 6 digit.',
        ]);

        Validator::make([
            'authorization_code' => $authorizationCode,
            'waba_id' => $wabaId,
            'phone_number_id' => $phoneNumberId,
        ], [
            'authorization_code' => ['required', 'string', 'max:4096'],
            'waba_id' => ['required', 'string', 'regex:/^\d+$/', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'regex:/^\d+$/', 'max:100'],
        ])->validate();

        try {
            $metaWhatsAppService->completeEmbeddedSignup(
                $authorizationCode,
                $wabaId,
                $phoneNumberId,
                $this->twoStepPin,
                Auth::user(),
            );

            $this->reset('twoStepPin');
            unset($this->integration);

            session()->flash('success', 'Nomor WhatsApp Business berhasil dihubungkan melalui Meta.');
        } catch (MetaWhatsAppException $exception) {
            $this->reset('twoStepPin');
            session()->flash('error', $exception->getMessage());
        }
    }

    public function checkConnection(MetaWhatsAppService $metaWhatsAppService): void
    {
        $this->authorizeAdmin();

        $integration = WhatsAppIntegration::current();

        if ($integration === null) {
            session()->flash('error', 'Belum ada integrasi WhatsApp yang tersimpan.');

            return;
        }

        try {
            $metaWhatsAppService->verifyConnection($integration);
            unset($this->integration);

            session()->flash('success', 'Koneksi WhatsApp ke Meta masih aktif.');
        } catch (MetaWhatsAppException $exception) {
            unset($this->integration);
            session()->flash('error', $exception->getMessage());
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
    }
};
?>

<div
    data-whatsapp-embedded-signup
    data-livewire-id="{{ $this->getId() }}"
    data-meta-app-id="{{ config('services.meta_whatsapp.app_id') }}"
    data-meta-config-id="{{ config('services.meta_whatsapp.login_config_id') }}"
    data-meta-graph-version="{{ config('services.meta_whatsapp.graph_version') }}"
    data-meta-configured="{{ $this->isConfigured ? 'true' : 'false' }}"
>
    <div class="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="text-xl font-semibold text-heading">Pengaturan WhatsApp Business</h1>
            <p class="mt-1 text-sm text-body">Hubungkan nomor WhatsApp Business App ke Cloud API dengan mode Coexistence.</p>
        </div>
        <a href="{{ route('admin.penjualan.index') }}" wire:navigate
            class="inline-flex items-center justify-center rounded-md border border-default-medium bg-white px-4 py-2.5 text-sm font-medium text-heading shadow-xs hover:bg-neutral-secondary-medium focus:outline-none focus:ring-4 focus:ring-neutral-tertiary">
            Kembali ke Penjualan
        </a>
    </div>

    @if (session()->has('success'))
        <div class="mb-4 rounded-lg bg-green-50 p-4 text-sm text-green-800" role="alert">
            <span class="font-medium">Sukses!</span> {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="mb-4 rounded-lg bg-red-50 p-4 text-sm text-red-800" role="alert">
            <span class="font-medium">Gagal!</span> {{ session('error') }}
        </div>
    @endif

    @unless($this->isConfigured)
        <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
            Lengkapi <code>META_APP_ID</code>, <code>META_APP_SECRET</code>, <code>META_LOGIN_CONFIG_ID</code>, dan <code>META_GRAPH_VERSION</code> pada environment server terlebih dahulu.
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <section class="rounded-lg border border-default bg-neutral-primary-soft p-5 shadow-xs lg:col-span-2">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-body">Status integrasi</p>
                    <div class="mt-2 flex items-center gap-2">
                        @if($this->integration?->isConnected())
                            <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">Terhubung</span>
                        @elseif($this->integration?->status === \App\Models\WhatsAppIntegration::STATUS_NEEDS_RECONNECT)
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">Perlu dihubungkan ulang</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Belum terhubung</span>
                        @endif
                    </div>
                </div>

                @if($this->integration)
                    <button type="button" wire:click="checkConnection" wire:loading.attr="disabled" wire:target="checkConnection"
                        class="inline-flex items-center justify-center rounded-md border border-default-medium bg-white px-4 py-2.5 text-sm font-medium text-heading shadow-xs hover:bg-neutral-secondary-medium focus:outline-none focus:ring-4 focus:ring-neutral-tertiary disabled:cursor-not-allowed disabled:opacity-50">
                        <span wire:loading.remove wire:target="checkConnection">Cek Koneksi</span>
                        <span wire:loading wire:target="checkConnection">Memeriksa...</span>
                    </button>
                @endif
            </div>

            <dl class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-md border border-default-medium bg-white p-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-body">Nomor Pengirim</dt>
                    <dd class="mt-1 text-sm font-semibold text-heading">{{ $this->integration?->display_phone_number ?? '-' }}</dd>
                </div>
                <div class="rounded-md border border-default-medium bg-white p-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-body">Verified Name</dt>
                    <dd class="mt-1 text-sm font-semibold text-heading">{{ $this->integration?->verified_name ?? '-' }}</dd>
                </div>
                <div class="rounded-md border border-default-medium bg-white p-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-body">WABA ID</dt>
                    <dd class="mt-1 break-all text-sm font-semibold text-heading">{{ $this->integration?->waba_id ?? '-' }}</dd>
                </div>
                <div class="rounded-md border border-default-medium bg-white p-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-body">Terakhir Diverifikasi</dt>
                    <dd class="mt-1 text-sm font-semibold text-heading">{{ $this->integration?->last_verified_at?->format('d M Y H:i') ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg border border-default bg-neutral-primary-soft p-5 shadow-xs">
            <h2 class="text-base font-semibold text-heading">Hubungkan dengan Meta</h2>
            <p class="mt-1 text-sm text-body">Masukkan PIN two-step verification nomor pengirim. PIN hanya dipakai saat registrasi dan tidak disimpan.</p>

            <div class="mt-5">
                <label for="twoStepPin" class="mb-2 block text-sm font-medium text-heading">PIN 6 digit</label>
                <input id="twoStepPin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required
                    wire:model.live.debounce.250ms="twoStepPin" data-whatsapp-pin autocomplete="one-time-code"
                    class="block w-full rounded-md border border-default-medium bg-white px-3 py-2.5 text-sm text-heading shadow-xs focus:border-brand focus:ring-brand"
                    placeholder="••••••">
                @error('twoStepPin')
                    <span class="mt-1 block text-xs text-red-600">{{ $message }}</span>
                @enderror
            </div>

            <button type="button" data-whatsapp-connect @disabled(! $this->isConfigured)
                wire:loading.attr="disabled" wire:target="completeWhatsAppSignup"
                class="mt-4 inline-flex w-full items-center justify-center rounded-md bg-green-600 px-4 py-2.5 text-sm font-medium text-white shadow-xs hover:bg-green-700 focus:outline-none focus:ring-4 focus:ring-green-200 disabled:cursor-not-allowed disabled:opacity-50">
                {{ $this->integration ? 'Hubungkan Ulang' : 'Hubungkan WhatsApp Business' }}
            </button>

            <p data-whatsapp-feedback class="mt-3 text-xs text-body" aria-live="polite">
                SDK Meta akan dimuat hanya pada halaman ini.
            </p>
        </section>
    </div>

    <section class="mt-6 rounded-lg border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
        <h2 class="font-semibold">Sebelum menghubungkan</h2>
        <ul class="mt-2 list-disc space-y-1 ps-5">
            <li>Buka halaman ini melalui domain HTTPS yang terdaftar pada Meta App.</li>
            <li>Pastikan Facebook Login for Business memakai konfigurasi WhatsApp Embedded Signup.</li>
            <li>Pilih opsi untuk menghubungkan WhatsApp Business App agar nomor tetap berjalan dalam mode Coexistence.</li>
        </ul>
    </section>
</div>
