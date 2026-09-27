<?php

namespace Database\Factories;

use App\Enums\SubscriptionPeriodStatus;
use App\Models\Subscription;
use App\Models\SubscriptionPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionPeriod>
 */
class SubscriptionPeriodFactory extends Factory
{
    protected $model = SubscriptionPeriod::class;

    public function definition(): array
    {
        $gross = 2900;
        $platform = (int) round($gross * 0.30);
        $pool = $gross - $platform;

        return [
            'subscription_id' => Subscription::factory(),
            'period_number' => 1,
            'start_at' => now()->startOfMonth(),
            'end_at' => now()->endOfMonth(),
            'gross_amount_in_cents' => $gross,
            'platform_amount_in_cents' => $platform,
            'instructor_pool_in_cents' => $pool,
            'status' => SubscriptionPeriodStatus::PENDING,
            'allocated_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::PENDING,
            'allocated_at' => null,
        ]);
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::OPEN,
            'allocated_at' => null,
        ]);
    }

    public function allocated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::ALLOCATED,
            'allocated_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionPeriodStatus::CANCELLED,
            'allocated_at' => null,
        ]);
    }
}
