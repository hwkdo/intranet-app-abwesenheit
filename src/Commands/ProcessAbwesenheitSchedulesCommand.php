<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Commands;

use Hwkdo\IntranetAppAbwesenheit\Services\AbwesenheitScheduleProcessor;
use Illuminate\Console\Command;

class ProcessAbwesenheitSchedulesCommand extends Command
{
    protected $signature = 'intranet-app-abwesenheit:process-schedules';

    protected $description = 'Wendet geplante Abwesenheiten an und beendet abgelaufene.';

    public function handle(AbwesenheitScheduleProcessor $processor): int
    {
        $processor->processDueSchedules();

        $this->info('Geplante Abwesenheiten verarbeitet.');

        return self::SUCCESS;
    }
}
