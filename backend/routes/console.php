<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Sanctum Token Pruning
|--------------------------------------------------------------------------
|
| Remove tokens that have been expired for at least 24 hours.
| Sanctum rejects expired tokens independently of this cleanup task.
|
*/

Schedule::command('sanctum:prune-expired --hours=24')
    ->daily();
