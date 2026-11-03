<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('budgets:auto')->dailyAt('00:05');
Schedule::command('recurring:create')->dailyAt('00:10');
