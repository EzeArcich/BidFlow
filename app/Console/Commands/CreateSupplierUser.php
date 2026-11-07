<?php

namespace App\Console\Commands;

use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateSupplierUser extends Command
{
    protected $signature = 'bidding:supplier-user {supplier} {email} {name}';

    protected $description = 'Provisiona una identidad de proveedor; solicita la contraseña sin mostrarla';

    public function handle(): int
    {
        $supplier = Supplier::find($this->argument('supplier'));
        if (! $supplier) {
            $this->error('Proveedor inexistente.');

            return self::FAILURE;
        }
        $data = ['email' => $this->argument('email'), 'name' => $this->argument('name'), 'password' => $this->secret('Contraseña (mínimo 12 caracteres)')];
        $validator = Validator::make($data, ['email' => 'required|email|max:255|unique:supplier_users,email', 'name' => 'required|string|max:255', 'password' => 'required|string|min:12|max:1024']);
        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }
        SupplierUser::create([...$data, 'supplier_id' => $supplier->id, 'active' => true]);
        $this->info('Cuenta de proveedor creada.');

        return self::SUCCESS;
    }
}
