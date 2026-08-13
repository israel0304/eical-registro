<?php

namespace App\Console\Commands;

use App\Services\ProgramService;
use Illuminate\Console\Command;

class ProgramaSync extends Command
{
    protected $signature = 'programa:sync';

    protected $description = 'Sincroniza el programa: crea los items del programa para todas las actividades registradas';

    public function handle(): int
    {
        ProgramService::ensureActivityItemsSynced();

        $this->info('Programa sincronizado correctamente.');

        return self::SUCCESS;
    }
}
