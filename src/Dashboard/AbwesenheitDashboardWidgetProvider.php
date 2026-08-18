<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Dashboard;

use Hwkdo\IntranetAppBase\Data\DashboardWidgetDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;

class AbwesenheitDashboardWidgetProvider implements DashboardWidgetProviderInterface
{
    public const KEY_MEIN_ABWESENHEITSSTATUS = 'mein-abwesenheitsstatus';

    /**
     * @return array<DashboardWidgetDefinition>
     */
    public static function widgets(): array
    {
        return [
            new DashboardWidgetDefinition(
                key: self::KEY_MEIN_ABWESENHEITSSTATUS,
                title: 'Mein Abwesenheitsstatus',
                description: 'Ihr aktueller Status in Outlook, Telefon und d3',
                component: 'intranet-app-abwesenheit::apps.abwesenheit.widgets.mein-abwesenheitsstatus',
                defaultW: 6,
                defaultH: 4,
                minW: 4,
                minH: 3,
                defaultEnabled: true,
                supportsItemCount: false,
            ),
        ];
    }
}
