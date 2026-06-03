<?php

namespace Hwkdo\IntranetAppAbwesenheit\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit
 */
class IntranetAppAbwesenheit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Hwkdo\IntranetAppAbwesenheit\IntranetAppAbwesenheit::class;
    }
}
