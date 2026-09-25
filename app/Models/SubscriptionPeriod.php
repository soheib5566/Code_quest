<?php

namespace App\Models;

use App\Enums\SubscriptionPeriodStatus;
use App\Models\CourseEngagement;
use App\Models\LedgerEntry;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SubscriptionPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'period_number',
        'start_at',
        'end_at',
        'gross_amount_in_cents',
        'platform_amount_in_cents',
        'instructor_pool_in_cents',
        'status',
        'allocated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionPeriodStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'allocated_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function engagements(): HasMany
    {
        return $this->hasMany(CourseEngagement::class, 'subscription_period_id');
    }

    public function ledgerEntries(): MorphMany
    {
        return $this->morphMany(LedgerEntry::class, 'source');
    }
}
