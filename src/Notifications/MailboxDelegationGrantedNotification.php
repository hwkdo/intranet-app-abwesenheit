<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Notifications;

class MailboxDelegationGrantedNotification extends MailboxDelegationNotification
{
    public function typeKey(): string
    {
        return 'abwesenheit.mailbox_delegation_granted';
    }

    protected function title(): string
    {
        return $this->forDelegate
            ? 'Postfach-Zugriff erhalten'
            : 'Postfach-Zugriff erteilt';
    }

    protected function body(): string
    {
        if ($this->forDelegate) {
            return 'Sie haben jetzt Vollzugriff auf das Outlook-Postfach von '.$this->ownerName.'.';
        }

        return $this->delegateName.' hat jetzt Vollzugriff auf Ihr Outlook-Postfach.';
    }
}
