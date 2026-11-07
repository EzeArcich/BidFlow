<?php

namespace App\Domain\Shared\Enums;

enum TenderType: string
{
    case Products = 'products';
    case Services = 'services';
    case Works = 'works';

    // Legacy demo values remain readable.
    case Parts = 'parts';
    case Repair = 'repair';
}
