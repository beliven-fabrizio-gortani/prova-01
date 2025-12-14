<?php

namespace Beliven\Prova01\Commands;

use Illuminate\Console\Command;

class Prova01Command extends Command
{
    public $signature = 'prova-01';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
