<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Delete expired "Try demo" gyms and accounts (needs the cron: * * * * * php artisan schedule:run).
Schedule::command('demo:purge')->hourly()->withoutOverlapping();

// Delete old records that hold personal data (e.g. handled access requests).
Schedule::command('model:prune')->daily()->withoutOverlapping();

// UTC end instants are checked at local midnight; display status remains date-driven between runs.
Schedule::command('memberships:expire')->daily()->timezone('Asia/Manila')->withoutOverlapping();
