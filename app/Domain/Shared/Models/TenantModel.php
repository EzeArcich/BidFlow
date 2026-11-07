<?php

namespace App\Domain\Shared\Models;

use App\Domain\Organizations\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

abstract class TenantModel extends DomainModel
{
    protected static function booted(): void
    {
        static::addGlobalScope('organization', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('organization_id'), app(TenantContext::class)->id());
        });

        static::saving(function (self $model): void {
            $organizationId = app(TenantContext::class)->id();
            if ($model->organization_id !== null && $model->organization_id !== $organizationId) {
                throw new LogicException('Cannot write outside the active organization.');
            }
            if ($model->exists && $model->isDirty('organization_id')) {
                throw new LogicException('Organization ownership is immutable.');
            }
            $model->organization_id = $organizationId;
        });
        static::deleting(function (self $model): void {
            if ($model->organization_id !== app(TenantContext::class)->id()) {
                throw new LogicException('Cannot delete outside the active organization.');
            }
        });
    }
}
