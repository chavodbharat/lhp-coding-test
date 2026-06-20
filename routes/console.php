<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:send --window=3day')->daily()->name('reminders.3day');
Schedule::command('reminders:send --window=24h')->hourly()->name('reminders.24h');
