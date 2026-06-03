<?php

namespace Hwkdo\IntranetAppAbwesenheit\Commands;

use Illuminate\Console\Command;

class IntranetAppAbwesenheitCommand extends Command
{
    public $signature = 'intranet-app-abwesenheit';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
