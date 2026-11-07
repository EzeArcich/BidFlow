<?php

namespace App\Domain\Tenders\Models;

use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepairScope extends TenantModel
{
    protected $table = 'repair_scopes';

    protected function casts(): array
    {
        return [
            'vehicle' => 'array',
            'location' => 'array',
            'required_tasks' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
