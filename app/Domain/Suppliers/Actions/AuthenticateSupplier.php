<?php

namespace App\Domain\Suppliers\Actions;

use App\Domain\Suppliers\Models\SupplierUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateSupplier
{
    public function execute(array $data): SupplierUser
    {
        $user = SupplierUser::where('email', $data['email'])->where('active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password) || ! $user->supplier()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['email' => 'Credenciales de proveedor inválidas.']);
        }

        return $user;
    }
}
