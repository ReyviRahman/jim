<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppIntegration extends Model
{
    public const PROVIDER = 'meta_whatsapp';

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_NEEDS_RECONNECT = 'needs_reconnect';

    protected $table = 'whatsapp_integrations';

    protected $fillable = [
        'provider',
        'waba_id',
        'phone_number_id',
        'display_phone_number',
        'verified_name',
        'access_token',
        'token_expires_at',
        'status',
        'connected_by_user_id',
        'connected_at',
        'last_verified_at',
    ];

    protected $hidden = [
        'access_token',
    ];

    protected $attributes = [
        'provider' => self::PROVIDER,
        'status' => self::STATUS_DISCONNECTED,
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by_user_id');
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED
            && filled($this->access_token)
            && filled($this->phone_number_id)
            && ($this->token_expires_at === null || $this->token_expires_at->isFuture());
    }

    public static function current(): ?self
    {
        return self::query()
            ->where('provider', self::PROVIDER)
            ->first();
    }
}
