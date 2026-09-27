<?php

namespace Database\Factories;

use App\Enums\LedgerDirection;
use App\Enums\LedgerStatus;
use App\Enums\LedgerType;
use App\Models\LedgerEntry;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LedgerEntry>
 */
class LedgerEntryFactory extends Factory
{
    protected $model = LedgerEntry::class;

    public function definition(): array
    {
        return [
            'instructor_id' => User::factory()->instructor(),
            'type' => LedgerType::EARNING,
            'direction' => LedgerDirection::CREDIT,
            'amount_in_cents' => fake()->numberBetween(500, 7500),
            'status' => LedgerStatus::PAYABLE,
            'source_type' => SubscriptionPeriod::class,
            'source_id' => 1,
            'payout_id' => null,
            'idempotency_key' => 'alloc_' . Str::random(20),
            'description' => 'Course consumption revenue share',
        ];
    }

    public function payable(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LedgerStatus::PAYABLE,
            'payout_id' => null,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LedgerStatus::LOCKED,
        ]);
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LedgerStatus::SETTLED,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => LedgerStatus::CANCELLED,
        ]);
    }

    public function credit(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => LedgerDirection::CREDIT,
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn (array $attributes) => [
            'direction' => LedgerDirection::DEBIT,
        ]);
    }

    public function clawback(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LedgerType::REFUND_CLAWBACK,
            'direction' => LedgerDirection::DEBIT,
            'description' => 'Subscription refund clawback',
        ]);
    }
}
