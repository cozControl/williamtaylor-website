<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('content:publish-scheduled-pages')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('site-content:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('payments:reconcile-snippe --limit=10')->everyFiveMinutes()->withoutOverlapping(10)->when(fn () => config('snippe.enabled'));
