<?php

namespace Tests\Unit\Services;

use App\Models\MediaItem;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\EmisionStartExceptionFactory;
use App\Services\ScheduledPositionCalculator;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ScheduledPositionCalculatorTest extends TestCase
{
    private function makeCalculator(): ScheduledPositionCalculator
    {
        return new ScheduledPositionCalculator(new EmisionStartExceptionFactory);
    }

    private function makePlaylist(bool $loop): Playlist
    {
        $p = new Playlist;
        $p->id = 'pl-1';
        $p->name = 'Test Playlist';
        $p->loop = $loop;

        return $p;
    }

    /**
     * Construye un PlaylistItem con un mediaItem embebido via setRelation,
     * de modo que effectiveDuration() funcione sin tocar la base de datos.
     */
    private function makeItem(int $position, float $durationSec, ?float $cueIn = null, ?float $cueOut = null): PlaylistItem
    {
        $item = new PlaylistItem;
        $item->id = 'item-'.$position;
        $item->position = $position;
        $item->cue_in_sec = $cueIn;
        $item->cue_out_sec = $cueOut;

        $media = new MediaItem;
        $media->id = 'media-'.$position;
        $media->duration_sec = $durationSec;
        $media->status = 'ready';

        $item->setRelation('mediaItem', $media);

        return $item;
    }

    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-07-20 '.$time);
    }

    public function test_midnight_exact_returns_first_item_offset_zero(): void
    {
        $playlist = $this->makePlaylist(loop: false);
        $items = collect([
            $this->makeItem(1, 120),
            $this->makeItem(2, 300),
            $this->makeItem(3, 180),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('00:00:00'));

        $this->assertSame(1, $result['item']->position);
        $this->assertSame(0.0, $result['offset_sec']);
    }

    public function test_no_loop_elapsed_exceeds_total_returns_last_item_at_full_offset(): void
    {
        // Duraciones: 1200 + 1800 = 3000s. 10:00 = 36000s > 3000.
        $playlist = $this->makePlaylist(loop: false);
        $items = collect([
            $this->makeItem(1, 1200),
            $this->makeItem(2, 1800),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('10:00:00'));

        $this->assertSame(2, $result['item']->position);
        $this->assertSame(1800.0, $result['offset_sec']);
    }

    public function test_loop_at_1353_returns_second_item_offset_780(): void
    {
        // 13:53 = 49980s. total = 3000. 49980 % 3000 = 1980.
        // item1 cubre [0,1200), item2 cubre [1200,3000) -> item2 @ 780.
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 1200),
            $this->makeItem(2, 1800),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('13:53:00'));

        $this->assertSame(2, $result['item']->position);
        $this->assertEqualsWithDelta(780.0, $result['offset_sec'], 0.01);
    }

    public function test_loop_single_item_at_0030_returns_offset_1800(): void
    {
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 3600),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('00:30:00'));

        $this->assertSame(1, $result['item']->position);
        $this->assertSame(1800.0, $result['offset_sec']);
    }

    public function test_empty_items_throws_exception(): void
    {
        $playlist = $this->makePlaylist(loop: false);
        $items = collect([]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/items validos/');

        $this->makeCalculator()->calculate($playlist, $items, $this->at('12:00:00'));
    }

    public function test_loop_with_zero_total_duration_throws_exception(): void
    {
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 0),
            $this->makeItem(2, 0),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/duracion total es 0/');

        $this->makeCalculator()->calculate($playlist, $items, $this->at('12:00:00'));
    }

    public function test_cue_in_out_respected_in_effective_duration(): void
    {
        // Item 1: media 1000s, cue_in=0, cue_out=200 -> eff 200
        // Item 2: media 1000s, cue_in=100, cue_out=400 -> eff 300
        // total = 500. loop=true. 00:03:00 = 180s -> item1 [0,200) no, 180<200 si -> item1 @ 180.
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 1000, cueIn: 0.0, cueOut: 200.0),
            $this->makeItem(2, 1000, cueIn: 100.0, cueOut: 400.0),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('00:03:00'));

        $this->assertSame(1, $result['item']->position);
        $this->assertSame(180.0, $result['offset_sec']);
    }

    public function test_no_loop_within_total_returns_correct_item(): void
    {
        // total = 1200+1800 = 3000. 00:10:00 = 600s -> item1 [0,1200) -> item1 @ 600.
        $playlist = $this->makePlaylist(loop: false);
        $items = collect([
            $this->makeItem(1, 1200),
            $this->makeItem(2, 1800),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('00:10:00'));

        $this->assertSame(1, $result['item']->position);
        $this->assertSame(600.0, $result['offset_sec']);
    }

    public function test_loop_wraps_multiple_times_correctly(): void
    {
        // total = 100s. loop=true. 01:00:00 = 3600s. 3600 % 100 = 0 -> item1 @ 0.
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 60),
            $this->makeItem(2, 40),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('01:00:00'));

        $this->assertSame(1, $result['item']->position);
        $this->assertSame(0.0, $result['offset_sec']);
    }

    public function test_loop_at_0020_with_30s_total_returns_item2_offset_10(): void
    {
        // 00:00:20 -> 20s. loop=true. total=30. 20%30=20.
        // item1 cubre [0,10), item2 cubre [10,30) -> item2 @ 10.
        $playlist = $this->makePlaylist(loop: true);
        $items = collect([
            $this->makeItem(1, 10),
            $this->makeItem(2, 20),
        ]);

        $result = $this->makeCalculator()->calculate($playlist, $items, $this->at('00:00:20'));

        $this->assertSame(2, $result['item']->position);
        $this->assertSame(10.0, $result['offset_sec']);
    }
}
