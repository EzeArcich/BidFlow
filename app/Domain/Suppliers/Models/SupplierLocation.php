<?php

namespace App\Domain\Suppliers\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierLocation extends DomainModel
{
    protected $table = 'supplier_locations';

    protected function casts(): array
    {
        return [
            'coverage' => 'array',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
