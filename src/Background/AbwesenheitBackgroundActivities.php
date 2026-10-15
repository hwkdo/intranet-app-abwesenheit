<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Background;

use Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityDefinition;
use Hwkdo\IntranetAppBase\Data\BackgroundActivityFrequency;
use Hwkdo\IntranetAppBase\Enums\BackgroundActivityCadence;

final class AbwesenheitBackgroundActivities
{
    /**
     * @return list<BackgroundActivityDefinition>
     */
    public static function definitions(): array
    {
        return [
            BackgroundActivityDefinition::scheduled(
                key: 'abwesenheit.process-schedules',
                label: 'Abwesenheiten anwenden',
                appIdentifier: IntranetAppAbwesenheit::identifier(),
                appName: IntranetAppAbwesenheit::app_name(),
                command: 'intranet-app-abwesenheit:process-schedules',
                frequency: BackgroundActivityFrequency::everyMinutes(1),
                description: 'Wendet geplante Abwesenheiten an und beendet abgelaufene.',
                allowedCadences: BackgroundActivityCadence::cases(),
                minEvery: 1,
                maxEvery: 60,
                withoutOverlappingMinutes: 1440,
            ),
        ];
    }
}
