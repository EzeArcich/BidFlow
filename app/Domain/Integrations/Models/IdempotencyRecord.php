<?php

namespace App\Domain\Integrations\Models;

use App\Domain\Organizations\Models\ApiClient;
use App\Domain\Organizations\Models\Organization;
use App\Domain\Shared\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdempotencyRecord extends TenantModel
{
    protected $table = 'idempotency_records';

    protected $hidden = ['response_body'];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}
