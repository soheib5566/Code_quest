<?php

namespace App\Models;

use App\Enums\TermPlan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'term',
        'price_in_cents',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'term' => TermPlan::class,
            'price_in_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
