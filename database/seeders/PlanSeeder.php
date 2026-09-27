<?php

namespace Database\Seeders;

use App\Enums\TermPlan;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Monthly Plan',
                'term' => TermPlan::MONTHLY,
                'price_in_cents' => 2900,
                'is_active' => true,
            ],
            [
                'name' => 'Quarterly Plan',
                'term' => TermPlan::QUARTERLY,
                'price_in_cents' => 7900,
                'is_active' => true,
            ],
            [
                'name' => 'Annual Plan',
                'term' => TermPlan::ANNUAL,
                'price_in_cents' => 24900,
                'is_active' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::firstOrCreate(
                ['term' => $plan['term']],
                $plan
            );
        }
    }
}
