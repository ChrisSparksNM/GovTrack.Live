<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily Congress Updates Scheduler
Schedule::command('scrape:congress-updates --congress=119 --days=2 --batch-size=100')
    ->dailyAt('06:00')
    ->name('daily-congress-updates')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Daily Congress updates completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Daily Congress updates failed');
    });

// Weekly full check for any missed updates
Schedule::command('scrape:congress-updates --congress=119 --days=7 --batch-size=50 --force-all')
    ->weeklyOn(0, '02:00') // Sunday at 2 AM
    ->name('weekly-congress-full-check')
    ->withoutOverlapping()
    ->runInBackground()
    ->onSuccess(function () {
        \Log::info('Weekly Congress full check completed successfully');
    })
    ->onFailure(function () {
        \Log::error('Weekly Congress full check failed');
    });

// Daily database statistics logging
Schedule::command('db:stats --congress=119')
    ->dailyAt('23:30')
    ->name('daily-db-stats')
    ->onSuccess(function () {
        \Log::info('Daily database statistics logged');
    });
