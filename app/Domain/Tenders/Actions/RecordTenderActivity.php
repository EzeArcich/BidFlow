<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Shared\ActivityContext;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;

class RecordTenderActivity
{
    public function execute(Tender $tender, User $actor, string $action, ?array $before = null, ?array $after = null): void
    {
        AuditLog::create([
            'actor_id' => $actor->id, 'action' => $action,
            'entity_type' => 'tender', 'entity_id' => $tender->id,
            'before' => $before, 'after' => $after,
            'origin' => app(ActivityContext::class)->origin, 'occurred_at' => now(),
        ]);
    }
}
