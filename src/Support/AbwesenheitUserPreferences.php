<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Support;

use Hwkdo\IntranetAppAbwesenheit\Data\UserSettings;
use Illuminate\Contracts\Auth\Authenticatable;

final class AbwesenheitUserPreferences
{
    public static function allowsSupervisorMailboxDelegationFor(?Authenticatable $user): bool
    {
        $settings = data_get($user, 'settings.app.abwesenheit');

        if ($settings instanceof UserSettings) {
            return $settings->allowSupervisorMailboxDelegation;
        }

        return false;
    }
}
