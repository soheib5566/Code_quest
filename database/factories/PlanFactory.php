<?php

namespace Database\Factories;

use App\Enums\TermPlan;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Starter', 'Pro', 'Premium', 'Ultimate']) . ' Plan',
            'term' => TermPlan::MONTHLY,
            'price_in_cents' => 2900,
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Monthly Plan',
            'term' => TermPlan::MONTHLY,
            'price_in_cents' => 2900,
        ]);
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Quarterly Plan',
            'term' => TermPlan::QUARTERLY,
            'price_in_cents' => 7900,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Annual Plan',
            'term' => TermPlan::ANNUAL,
            'price_in_cents' => 24900,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
