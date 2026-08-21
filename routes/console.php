<?php

use App\Console\Commands\RecalculateKpisCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(RecalculateKpisCommand::class, ['daily'])->dailyAt('06:00');
Schedule::command(RecalculateKpisCommand::class, ['weekly'])->weeklyOn(1, '06:15');
Schedule::command(RecalculateKpisCommand::class, ['fortnightly'])->weeklyOn(1, '06:30');
Schedule::command(RecalculateKpisCommand::class, ['monthly'])->monthlyOn(1, '06:45');
Schedule::command(RecalculateKpisCommand::class, ['termly'])->dailyAt('07:00');
