<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Notifications;

class MailboxDelegationRevokedNotification extends MailboxDelegationNotification
{
    public function typeKey(): string
    {
        return 'abwesenheit.mailbox_delegation_revoked';
    }

    protected function title(): string
    {
        return $this->forDelegate
            ? 'Postfach-Zugriff entzogen'
            : 'Postfach-Zugriff entfernt';
    }

    protected function body(): string
    {
        if ($this->forDelegate) {
            return 'Ihr Vollzugriff auf das Outlook-Postfach von '.$this->ownerName.' wurde entfernt.';
        }

        return 'Der Vollzugriff von '.$this->delegateName.' auf Ihr Outlook-Postfach wurde entfernt.';
    }
}
