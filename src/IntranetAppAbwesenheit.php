<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit;

use Hwkdo\IntranetAppAbwesenheit\Background\AbwesenheitBackgroundActivities;
use Hwkdo\IntranetAppAbwesenheit\Dashboard\AbwesenheitDashboardWidgetProvider;
use Hwkdo\IntranetAppAbwesenheit\Data\AppSettings;
use Hwkdo\IntranetAppAbwesenheit\Data\UserSettings;
use Hwkdo\IntranetAppBase\Data\NotificationTypeDefinition;
use Hwkdo\IntranetAppBase\Interfaces\DashboardWidgetProviderInterface;
use Hwkdo\IntranetAppBase\Interfaces\IntranetAppInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesBackgroundActivitiesInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesDashboardWidgetsInterface;
use Hwkdo\IntranetAppBase\Interfaces\ProvidesNotificationsInterface;
use Illuminate\Support\Collection;

class IntranetAppAbwesenheit implements IntranetAppInterface, ProvidesBackgroundActivitiesInterface, ProvidesDashboardWidgetsInterface, ProvidesNotificationsInterface
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

    public static function backgroundActivities(): array
    {
        return AbwesenheitBackgroundActivities::definitions();
    }

    public static function notificationTypes(): array
    {
        return [
            new NotificationTypeDefinition(
                key: 'abwesenheit.mailbox_delegation_granted',
                label: 'Postfach-Zugriff erteilt',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Wenn jemand Vollzugriff auf ein Outlook-Postfach erhält.',
                mandatory: true,
                defaultChannels: ['inbox', 'mail'],
                requiredChannels: ['inbox', 'mail'],
            ),
            new NotificationTypeDefinition(
                key: 'abwesenheit.mailbox_delegation_revoked',
                label: 'Postfach-Zugriff entfernt',
                appIdentifier: self::identifier(),
                appName: self::app_name(),
                description: 'Wenn ein Vollzugriff auf ein Outlook-Postfach wieder entfernt wird.',
                mandatory: true,
                defaultChannels: ['inbox', 'mail'],
                requiredChannels: ['inbox', 'mail'],
            ),
        ];
    }
}
