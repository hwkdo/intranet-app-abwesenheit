<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Policies;

use Illuminate\Database\Eloquent\Model;

class AbwesenheitPolicy
{
    public function view(Model $actor, Model $target): bool
    {
        return $this->manage($actor, $target);
    }

    public function manage(Model $actor, Model $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        if (! $actor->ist_vorgesetzter) {
            return false;
        }

        $untergebene = $actor->getUntergebene(true);

        if ($untergebene === false) {
            return false;
        }

        return $untergebene->contains(fn (Model $user): bool => (int) $user->id === (int) $target->id);
    }

    public function delegateMailboxFor(Model $actor, Model $target): bool
    {
        if ((int) $actor->id === (int) $target->id) {
            return true;
        }

        if (! $this->manage($actor, $target)) {
            return false;
        }

        return (bool) $target->allow_supervisor_mailbox_delegation;
    }
}
