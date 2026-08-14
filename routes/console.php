<?php

use App\Jobs\PurgeSoftDeletedMedia;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(PurgeSoftDeletedMedia::class)->daily()->at('03:00')->name('purge-soft-deleted-media');
