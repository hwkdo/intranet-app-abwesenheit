<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

if (! app()->runningUnitTests()) {
    Schedule::command('intranet-app-abwesenheit:process-schedules')
        ->everyMinute()
        ->withoutOverlapping();
}
