<?php

namespace App\Services;

use RuntimeException;

class TimelineIntervalValidator
{
    /**
     * @param iterable<array{starts_at_sec:int, ends_at_sec:int, kind?:string}> $items
     */
    public function assertSequential(iterable $items): void
    {
        $previousEnd = null;

        foreach ($items as $item) {
            $start = (int) $item['starts_at_sec'];
            $end = (int) $item['ends_at_sec'];

            if ($end <= $start) {
                throw new RuntimeException('Timeline item must have a positive duration.');
            }

            if ($previousEnd !== null && $start < $previousEnd) {
                throw new RuntimeException('Timeline items cannot overlap.');
            }

            $previousEnd = $end;
        }
    }
}
