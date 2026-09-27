<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'instructor_id' => User::factory()->instructor(),
            'amount_in_cents' => fake()->numberBetween(2500, 75000),
            'status' => PayoutStatus::PENDING,
            'idempotency_key' => 'payout_' . Str::random(16),
            'external_reference' => null,
            'failure_reason' => null,
            'attempts' => 0,
            'paid_at' => null,
            'reconciled_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::PENDING,
            'attempts' => 0,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::PROCESSING,
            'attempts' => 1,
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::PAID,
            'external_reference' => 'gw_tx_' . Str::random(16),
            'attempts' => 1,
            'paid_at' => now()->subHours(fake()->numberBetween(1, 48)),
        ]);
    }

    public function inDoubt(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::IN_DOUBT,
            'attempts' => 1,
            'failure_reason' => 'Connection timed out waiting for gateway response',
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::FAILED,
            'attempts' => 1,
            'failure_reason' => 'Payment gateway rejected transfer: Invalid recipient IBAN account',
        ]);
    }
}
