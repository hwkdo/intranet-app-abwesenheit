<?php

declare(strict_types=1);

namespace Hwkdo\IntranetAppAbwesenheit\Data;

readonly class AbwesenheitApplyResult
{
    /**
     * @param  list<string>  $warnings
     */
    public function __construct(
        public array $warnings = [],
    ) {}
}
