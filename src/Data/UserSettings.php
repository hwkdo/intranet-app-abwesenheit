<?php

namespace Hwkdo\IntranetAppAbwesenheit\Data;

use Hwkdo\IntranetAppAbwesenheit\Enums\ViewModeEnum;
use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Standard-Anzeigemodus für die App')]
        public ViewModeEnum $defaultViewMode = ViewModeEnum::Grid,

        #[Description('Favoriten-Bereiche des Benutzers')]
        public array $favoriteAreas = [],

        #[Description('Benachrichtigungen aktiviert')]
        public bool $notificationsEnabled = true,
    ) {}
}
