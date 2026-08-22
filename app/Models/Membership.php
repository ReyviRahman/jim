<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Membership extends Model
{
    protected $attributes = [
        'admin_fee' => 0,
    ];

    protected $fillable = [
        'user_id',
        'type',
        'pt_id',
        'admin_id',
        'follow_up_id',
        'follow_up_id_two',
        'gym_package_id',
        'pt_package_id',
        'base_price',
        'discount_applied',
        'admin_fee',
        'price_paid',
        'normal_price',
        'net_price',
        'unrecommended_price',
        'total_paid',
        'payment_status',
        'total_sessions',
        'remaining_sessions',
        'member_goal',
        'start_date',
        'pt_end_date',
        'membership_end_date',
        'status',
        'is_active',
        'notes',
        'transaction_type',
        'package_name',
        'sesi_ditambahkan',
        'sesi_hangus',
    ];

    protected $casts = [
        'start_date' => 'date',
        'pt_end_date' => 'date',
        'membership_end_date' => 'date',
        'price_paid' => 'decimal:0',
        'admin_fee' => 'decimal:0',
        'total_sessions' => 'integer',
        'remaining_sessions' => 'integer',
        'sesi_ditambahkan' => 'integer',
        'sesi_hangus' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Relasi ke Pembayar / Pendaftar Utama
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function followUp(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_id');
    }

    public function followUpTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follow_up_id_two');
    }

    /**
     * Relasi ke SEMUA Anggota yang berhak masuk dengan membership ini
     * (Untuk mengambil data istri/teman di paket Couple/Group)
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'membership_users', 'membership_id', 'user_id')
            ->withTimestamps();
    }

    public function personalTrainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pt_id');
    }

    public function gymPackage(): BelongsTo
    {
        return $this->belongsTo(GymPackage::class, 'gym_package_id');
    }

    public function ptPackage()
    {
        return $this->belongsTo(GymPackage::class, 'pt_package_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'membership_users');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(MembershipTransaction::class);
    }

    public function ptSchedule(): HasOne
    {
        return $this->hasOne(PtSchedule::class);
    }

    public function ptBookings(): HasMany
    {
        return $this->hasMany(PtBooking::class)->orderBy('booking_date')->orderBy('booking_time');
    }

    public function scopeForBonusRecipient(Builder $query, User $staffUser): Builder
    {
        $query->where('type', '!=', 'visit');

        if ($staffUser->role === 'pt') {
            return $query
                ->whereIn('type', ['pt', 'membership'])
                ->whereBelongsTo($staffUser, 'followUp')
                ->whereBelongsTo($staffUser, 'followUpTwo');
        }

        return $query->where(function (Builder $query) use ($staffUser): void {
            $query->whereBelongsTo($staffUser, 'followUp')
                ->orWhereBelongsTo($staffUser, 'followUpTwo');
        });
    }

    public function getPriceLabel(): ?array
    {
        $pricePaid = (float) $this->price_paid;
        $normalPrice = (float) $this->normal_price;
        $netPrice = (float) $this->net_price;
        $unrecommendedPrice = (float) $this->unrecommended_price;

        $effectiveNormalPrice = $normalPrice > 0 ? $normalPrice : null;
        $effectiveNetPrice = $netPrice > 0 ? $netPrice : null;
        $effectiveUnrecommendedPrice = $unrecommendedPrice > 0 ? $unrecommendedPrice : null;

        if ($effectiveNormalPrice !== null && $pricePaid >= $effectiveNormalPrice) {
            return ['label' => 'Harga Normal', 'color' => 'bg-blue-100 text-blue-800'];
        }

        if ($effectiveNetPrice !== null && $pricePaid >= $effectiveNetPrice) {
            return ['label' => 'Harga Net', 'color' => 'bg-emerald-100 text-emerald-800'];
        }

        if ($pricePaid > 0 && ($effectiveNormalPrice !== null || $effectiveNetPrice !== null || $effectiveUnrecommendedPrice !== null)) {
            return ['label' => 'Harga Tidak Disarankan', 'color' => 'bg-red-100 text-red-800'];
        }

        return null;
    }

    public function getPtCategoryLabel(): string
    {
        return $this->pt_id === null ? 'SLS' : $this->getPtCategoryLabelFor((int) $this->pt_id);
    }

    public function getPtCategoryLabelFor(int $ptId): string
    {
        if ((int) $this->pt_id !== $ptId) {
            return 'SLS';
        }

        $followUpId = $this->follow_up_id === null ? $ptId : (int) $this->follow_up_id;
        $followUpIdTwo = $this->follow_up_id_two === null ? $ptId : (int) $this->follow_up_id_two;

        if ($followUpId !== $ptId || $followUpIdTwo !== $ptId) {
            return 'SLS';
        }

        $pricePaid = (float) $this->price_paid;
        $normalPrice = (float) $this->normal_price;
        $netPrice = (float) $this->net_price;

        if ($normalPrice > 0 && $pricePaid >= $normalPrice) {
            return 'SDR';
        }

        if ($netPrice > 0 && $pricePaid >= $netPrice) {
            return 'IR';
        }

        if ($normalPrice > 0 || $netPrice > 0 || (float) $this->unrecommended_price > 0) {
            return 'SPR';
        }

        return 'SDR';
    }

    public function calculateNominalAkhir(): float
    {
        $nominal = $this->total_paid ?? 0;

        $isSameEligibleFollowUp = $this->follow_up_id !== null
            && $this->follow_up_id_two !== null
            && (int) $this->follow_up_id === (int) $this->follow_up_id_two
            && in_array($this->followUp?->role, ['pt', 'kasir_gym'], true);

        if ($isSameEligibleFollowUp) {
            return $nominal;
        }

        $pricePaid = (float) $this->price_paid;
        $normalPrice = (float) $this->normal_price;
        $netPrice = (float) $this->net_price;
        $basePrice = (float) $this->base_price;

        $effectiveNormalPrice = $normalPrice > 0 ? $normalPrice : null;
        $effectiveNetPrice = $netPrice > 0 ? $netPrice : null;

        $isUnrecommended = false;

        if ($effectiveNormalPrice !== null && $pricePaid >= $effectiveNormalPrice) {
            $isUnrecommended = false;
        } elseif ($effectiveNetPrice !== null && $pricePaid >= $effectiveNetPrice) {
            $isUnrecommended = false;
        } elseif ($pricePaid > 0) {
            $isUnrecommended = true;
        }

        if ($isUnrecommended) {
            return $nominal / 2;
        }

        if ($this->follow_up_id && $this->follow_up_id_two && ($this->follow_up_id !== $this->follow_up_id_two)) {
            return $nominal / 2;
        }

        return $nominal;
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($membership) {
            $membership->transactions()->delete();
            $membership->members()->detach();
            $membership->ptSchedule()->delete();
        });
    }
}
