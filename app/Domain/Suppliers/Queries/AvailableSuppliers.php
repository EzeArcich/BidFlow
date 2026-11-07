<?php

namespace App\Domain\Suppliers\Queries;

use App\Domain\Suppliers\Models\Supplier;

class AvailableSuppliers
{
    public function execute(array $filters)
    {
        $query = Supplier::visibleToCurrentOrganization()->where('status', 'active')
            ->whereHas('organizationLinks', fn ($q) => $q->where('status', 'active'))
            ->with(['contacts', 'capabilities']);
        if (isset($filters['q'])) {
            $query->where(fn ($q) => $q->where('legal_name', 'ilike', '%'.$filters['q'].'%')->orWhere('trade_name', 'ilike', '%'.$filters['q'].'%'));
        }

        return $query->orderBy('trade_name')->orderBy('id')->paginate($filters['per_page'] ?? 20)->withQueryString();
    }
}
