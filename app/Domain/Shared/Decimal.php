<?php

namespace App\Domain\Shared;

use Illuminate\Validation\ValidationException;

final class Decimal
{
    public static function subtotal(string $quantity, string $price): string
    {
        $value = bcadd(bcadd(bcmul($quantity, $price, 5), '0.005', 5), '0', 2);
        self::amount($value);

        return $value;
    }

    public static function amount(string $value): void
    {
        if (bccomp($value, '9999999999999.99', 2) > 0) {
            throw ValidationException::withMessages(['lines' => 'El importe supera el máximo admitido.']);
        }
    }
}
