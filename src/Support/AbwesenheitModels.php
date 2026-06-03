<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AbwesenheitModels
{
    /**
     * @return class-string<Model>
     */
    public static function user(): string
    {
        return (string) config('intranet-app-abwesenheit.user_model');
    }

    /**
     * @return class-string<Model>
     */
    public static function gvp(): string
    {
        return (string) config('intranet-app-abwesenheit.gvp_model');
    }

    /**
     * @return Builder<Model>
     */
    public static function userQuery(): Builder
    {
        return static::user()::query();
    }
}
