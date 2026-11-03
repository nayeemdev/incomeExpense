<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('budgets:auto')->dailyAt('00:05');
Schedule::command('recurring:create')->dailyAt('00:10');
Schedule::command('notifications:bills')->dailyAt('08:00');
Schedule::command('notifications:weekly')->weeklyOn(1, '08:00');
Schedule::command('exchange-rates:fetch')->dailyAt('06:00');
