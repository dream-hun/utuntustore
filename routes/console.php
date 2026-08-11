<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| The sweep is what actually enforces the subscription business model: without
| it, a vendor who stopped paying would keep selling indefinitely.
|
| withoutOverlapping guards against a slow run on a large vendor table colliding
| with the next day's run.
|
*/

Schedule::command('subscriptions:sweep')
    ->dailyAt('00:15')
    ->withoutOverlapping()
    ->onOneServer();

// Runs after the sweep so a vendor who lapsed overnight is not also sent a
// "expiring soon" notice on the same morning.
Schedule::command('subscriptions:remind')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('carts:prune')
    ->weekly()
    ->onOneServer();
