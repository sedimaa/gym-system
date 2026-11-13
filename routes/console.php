<?php

use Illuminate\Support\Facades\Schedule;

/*
| Daily backup. Runs every hour but only makes a backup when none exists for today,
| so it still happens on days the gym PC is switched on late or off at closing time.
| Needs Windows Task Scheduler to run "php artisan schedule:run" every minute (see README).
*/
Schedule::command('gym:backup --if-missing-today')->hourly()->withoutOverlapping();
