<?php

namespace App\Services\Ledger;

class SplitCalculatorService
{
    /**
     * Summary of calculator
     * @param int $instructorCents
     * @param array<int, int> $instructorConsumption [instructor_id => watch_seconds]
     * @return array<int, int> [instructor_id => allocated_cents]
     */
    public function calculator(int $instructorCents, array $instructorConsumption): array
    {
        $consumptionUnits = array_sum($instructorConsumption);

        if ($consumptionUnits <= 0 || $instructorCents <= 0) {
            return [];
        }

        $allocated = [];
        $remainders = [];
        $totalDeservedCents = 0;

        foreach ($instructorConsumption as $instructorId => $units) {
            $raw = ($instructorCents * $units) / $consumptionUnits;
            $base = (int) floor($raw);
            $allocated[$instructorId] = $base;
            $remainders[$instructorId] = $raw - $base;
            $totalDeservedCents += $base;
        }

        $leftOvers = $instructorCents - $totalDeservedCents;

        uksort($remainders, function ($a, $b) use ($remainders) {
            if ($remainders[$a] === $remainders[$b]) {
                return $a <=> $b;
            }
            return $remainders[$b] <=> $remainders[$a];
        });

        // Distribute remainder pennies one by one
        foreach (array_keys($remainders) as $instructorId) {
            if ($leftOvers <= 0) {
                break;
            }

            $allocated[$instructorId] += 1;
            $leftOvers--;
        }

        return $allocated;
    }
}
