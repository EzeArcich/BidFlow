<?php

namespace App\Domain\Suppliers\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierCapability extends DomainModel
{
    protected $table = 'supplier_capabilities';

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
