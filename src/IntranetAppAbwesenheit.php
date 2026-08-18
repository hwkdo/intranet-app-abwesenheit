<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit;

use Hwkdo\IntranetAppAbwesenheit\Dashboard\AbwesenheitDashboardWidgetProvider;
use Hwkdo\IntranetAppAbwesenheit\Data\AppSettings;
use Hwkdo\IntranetAppAbwesenheit\Data\UserSettings;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Illuminate\Support\Collection;

class IntranetAppAbwesenheit implements IntranetAppInterface, ProvidesDashboardWidgetsInterface
{
    public static function app_name(): string
    {
        return 'Abwesenheit';
    }

    public static function app_icon(): string
    {
        return 'magnifying-glass';
    }

    public static function identifier(): string
    {
        return 'abwesenheit';
    }

    public static function roles_admin(): Collection
    {
        return collect(config('intranet-app-abwesenheit.roles.admin'));
    }

    public static function roles_user(): Collection
    {
        return collect(config('intranet-app-abwesenheit.roles.user'));
    }

    public static function userSettingsClass(): ?string
    {
        return UserSettings::class;
    }

    public static function appSettingsClass(): ?string
    {
        return AppSettings::class;
    }

    public static function mcpServers(): array
    {
        return [];
    }

    /**
     * @return array<class-string<DashboardWidgetProviderInterface>>
     */
    public static function dashboardWidgetProviders(): array
    {
        return [
            AbwesenheitDashboardWidgetProvider::class,
        ];
    }
}
