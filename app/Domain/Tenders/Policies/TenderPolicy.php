<?php

namespace App\Domain\Tenders\Policies;

use App\Domain\Organizations\Policies\TenantRecordPolicy;
use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Shared\Models\TenantModel;
use App\Models\User;

class TenderPolicy extends TenantRecordPolicy
{
    public function create(User $user): bool
    {
        return $this->hasRole($user, ['owner', 'admin', 'operator']);
    }

    public function manage(User $user, TenantModel $record): bool
    {
        return $this->view($user, $record) && $this->create($user);
    }

    public function publish(User $user, TenantModel $record): bool
    {
        return $this->manage($user, $record);
    }

    public function update(User $user, TenantModel $record): bool
    {
        return $this->view($user, $record) && $this->create($user) && $record->status === TenderStatus::Draft;
    }
}
