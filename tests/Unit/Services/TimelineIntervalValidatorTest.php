<?php

namespace Tests\Unit\Services;

use App\Services\TimelineIntervalValidator;
use RuntimeException;
use Tests\TestCase;

class TimelineIntervalValidatorTest extends TestCase
{
    public function test_split_cue_sequence_is_sequential(): void
    {
        $items = [
            ['starts_at_sec' => 0, 'ends_at_sec' => 1000, 'kind' => 'content'],
            ['starts_at_sec' => 1000, 'ends_at_sec' => 1045, 'kind' => 'cue'],
            ['starts_at_sec' => 1045, 'ends_at_sec' => 9500, 'kind' => 'content'],
        ];

        (new TimelineIntervalValidator)->assertSequential($items);

        $this->assertTrue(true);
    }

    public function test_overlapping_items_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('cannot overlap');

        (new TimelineIntervalValidator)->assertSequential([
            ['starts_at_sec' => 0, 'ends_at_sec' => 1000],
            ['starts_at_sec' => 999, 'ends_at_sec' => 1045],
        ]);
    }

    public function test_zero_duration_items_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('positive duration');

        (new TimelineIntervalValidator)->assertSequential([
            ['starts_at_sec' => 1000, 'ends_at_sec' => 1000],
        ]);
    }
}
