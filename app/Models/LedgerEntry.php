<?php

namespace App\Models;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'instructor_id',
        'type',
        'direction',
        'amount_in_cents',
        'status',
        'source_type',
        'source_id',
        'payout_id',
        'idempotency_key',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'status' => LedgerStatus::class,
            'type' => LedgerType::class,
            'direction' => LedgerDirection::class,
        ];
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePayable(Builder $query): Builder
    {
        return $query->where('status', 'payable');
    }

    public function scopeLocked(Builder $query): Builder
    {
        return $query->where('status', 'locked');
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', 'settled');
    }

    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('direction', 'credit');
    }

    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('direction', 'debit');
    }
}
