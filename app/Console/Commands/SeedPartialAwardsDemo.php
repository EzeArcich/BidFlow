<?php

namespace App\Console\Commands;

use Database\Seeders\PartialAwardsDemoSeeder;
use Illuminate\Console\Command;

class SeedPartialAwardsDemo extends Command
{
    protected $signature = 'bidding:seed-phase3-demo';

    protected $description = 'Agrega demo ficticia de portal y adjudicación parcial, sin modificar datos existentes';

    public function handle(PartialAwardsDemoSeeder $seeder): int
    {
        $password = $this->secret('Contraseña local para las cuatro cuentas nuevas (mínimo 12 caracteres)');
        try {
            $seeder->run((string) $password);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Demo agregada: comprador.fase3@example.test; portal1@example.test, portal2@example.test y portal3@example.test. Usar la contraseña elegida.');

        return self::SUCCESS;
    }
}
