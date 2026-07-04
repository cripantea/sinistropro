<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Promemoria giornalieri pratiche aperte — ogni mattina alle 07:00 ora italiana.
// withoutOverlapping() evita doppia esecuzione se un run precedente è ancora in corso.
// runInBackground() non blocca altri task schedulati.
Schedule::command('app:process-daily-reminders')
    ->dailyAt('07:00')
    ->timezone('Europe/Rome')
    ->withoutOverlapping(10)   // lock per max 10 minuti
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/daily-reminders.log'));
