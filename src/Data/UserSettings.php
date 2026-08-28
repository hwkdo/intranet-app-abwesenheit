<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Data;

use Hwkdo\IntranetAppBase\Data\Attributes\Description;
use Hwkdo\IntranetAppBase\Data\BaseUserSettings;

class UserSettings extends BaseUserSettings
{
    public function __construct(
        #[Description('Vorgesetzten erlauben, bei Abwesenheit mein Postfach zu delegieren. Wenn aktiviert, können Ihre Vorgesetzten bei Ihrer Abwesenheit Ihr Postfach an einen Vertreter delegieren.')]
        public bool $allowSupervisorMailboxDelegation = false,
    ) {}
}
