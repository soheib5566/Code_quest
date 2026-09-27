<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $plan = Plan::inRandomOrder()->first() ?? Plan::factory()->create();
        $durationMonths = $plan->term->durationInMonths();
        $startDate = now()->subDays(fake()->numberBetween(0, 15));

        return [
            'student_id' => User::factory()->student(),
            'plan_id' => $plan->id,
            'price_paid_in_cents' => $plan->price_in_cents,
            'platform_fee_percent' => 30,
            'status' => SubscriptionStatus::ACTIVE,
            'start_at' => $startDate,
            'end_at' => (clone $startDate)->addMonths($durationMonths),
            'payment_reference' => 'sub_order_' . Str::random(12),
            'cancelled_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::ACTIVE,
            'cancelled_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::REFUNDED,
            'cancelled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SubscriptionStatus::EXPIRED,
            'start_at' => now()->subMonths(14),
            'end_at' => now()->subMonths(2),
        ]);
    }
}
