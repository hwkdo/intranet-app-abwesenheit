<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Concerns;

use Hwkdo\IntranetAppAbwesenheit\Support\AbwesenheitModels;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasAbwesenheitGvpRelations
{
    /**
     * Legacy-Alias für GVP-Mitarbeiter.
     */
    public function mitarbeiter(): HasMany
    {
        return $this->users();
    }

    /**
     * Legacy-Alias für untergeordnete GVPs.
     */
    public function child(): HasMany
    {
        return $this->childGvps();
    }

    public function mitarbeiter_secondary(): BelongsToMany
    {
        return $this->belongsToMany(AbwesenheitModels::user(), 'gvp_user_secondary')
            ->withTimestamps();
    }
}
