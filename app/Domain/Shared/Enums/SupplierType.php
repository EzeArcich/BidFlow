<?php

namespace App\Domain\Shared\Enums;

enum SupplierType: string
{
    case Vendor = 'vendor';
    case Contractor = 'contractor';

    case Parts = 'parts';
    case Workshop = 'workshop';
    case Mixed = 'mixed';
}
