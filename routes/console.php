<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:send-event-reminder')
    ->dailyAt('08:00')
    ->timezone('UTC')
    ->runInBackground()
    ->withoutOverlapping();
