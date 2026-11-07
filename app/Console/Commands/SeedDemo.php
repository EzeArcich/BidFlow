<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedDemo extends Command
{
    protected $signature = 'bidding:seed-demo {--profile=demo : demo|development|stress}';

    protected $description = 'Carga datos ficticios sin enviar correos ni llamar servicios externos';

    public function handle(DemoSeeder $seeder): int
    {
        try {
            $seeder->run((string) $this->option('profile'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Demo cargada. Todos los proveedores, precios y compatibilidades son ficticios.');

        return self::SUCCESS;
    }
}
