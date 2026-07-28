<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('alerts:detect')->hourly();

// UI-28: daily/weekly cadence is decided inside the command itself
// (DigestSubscription.last_sent_at) — running once a day at a fixed time
// is enough to catch both; it just skips a subscription until it's due.
Schedule::command('digests:send')->dailyAt('07:00');

// REC-10 ("restore PG + S3 backup"): the database dump (spatie/laravel
// -backup) and the media-disk mirror (steriqore:backup-media) are two
// halves of the same nightly backup — both write to the 'backups' disk.
Schedule::command('backup:run --only-db')->dailyAt('02:00');
Schedule::command('steriqore:backup-media')->dailyAt('02:10');
Schedule::command('backup:clean')->dailyAt('03:00');
Schedule::command('backup:monitor')->dailyAt('04:00');
