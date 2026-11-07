<?php

namespace App\Domain\Bids\Policies;

use App\Domain\Bids\Models\Bid;
use App\Domain\Organizations\Policies\TenantRecordPolicy;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Models\User;

class BidPolicy
{
    public function view(User|SupplierUser $actor, Bid $bid): bool
    {
        return $actor instanceof SupplierUser ? $actor->active && $actor->supplier_id === $bid->supplier_id
         : app(TenantRecordPolicy::class)->view($actor, $bid) && in_array($bid->status->value, ['submitted', 'accepted', 'rejected']);
    }

    public function update(User|SupplierUser $actor, Bid $bid): bool
    {
        return $actor instanceof SupplierUser && $this->view($actor, $bid) && $bid->status->value === 'draft';
    }
}
