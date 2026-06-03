<?php

namespace Hwkdo\IntranetAppAbwesenheit\Models;

use Hwkdo\IntranetAppAbwesenheit\Data\AppSettings;
use Illuminate\Database\Eloquent\Model;

class IntranetAppAbwesenheitSettings extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => AppSettings::class.':default',
        ];
    }

    public static function current(): IntranetAppAbwesenheitSettings|null
    {
        return self::orderBy('version', 'desc')->first();
    }
}
