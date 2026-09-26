<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Ledger\SplitCalculatorService;

class RevenueSplitCalculatorTest extends TestCase
{
    private SplitCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new SplitCalculatorService();
    }

    public function test_it_solves_penny_rounding_without_leaking_cents(): void
    {
        // 1,000 cents split 3 ways equally -> 333 + 333 + 333 = 999 (+1 cent leftover)
        $pool = 1000;
        $consumption = [
            1 => 100, // Instructor 1
            2 => 100, // Instructor 2
            3 => 100, // Instructor 3
        ];

        $result = $this->calculator->calculator($pool, $consumption);

        // Sum must strictly equal 1000
        $this->assertSame(1000, array_sum($result));
        $this->assertSame(334, $result[1]); // Received deterministic remainder
        $this->assertSame(333, $result[2]);
        $this->assertSame(333, $result[3]);
    }

    public function test_it_splits_proportionally_based_on_consumption_weight(): void
    {
        $pool = 10000; // $100.00
        $consumption = [
            1 => 700, // 70%
            2 => 300, // 30%
        ];

        $result = $this->calculator->calculator($pool, $consumption);

        $this->assertSame(10000, array_sum($result));
        $this->assertSame(7000, $result[1]);
        $this->assertSame(3000, $result[2]);
    }

    public function test_it_handles_empty_or_zero_consumption_safely(): void
    {
        $this->assertSame([], $this->calculator->calculator(1000, []));
        $this->assertSame([], $this->calculator->calculator(1000, [1 => 0, 2 => 0]));
        $this->assertSame([], $this->calculator->calculator(0, [1 => 100]));
    }
}
