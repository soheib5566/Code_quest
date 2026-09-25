<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use App\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'amount_in_cents',
        'status',
        'idempotency_key',
        'external_reference',
        'failure_reason',
        'attempts',
        'paid_at',
        'reconciled_at',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'reconciled_at' => 'datetime',
            'status' => PayoutStatus::class,
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeInDoubt(Builder $query): Builder
    {
        return $query->where('status', 'in_doubt');
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
