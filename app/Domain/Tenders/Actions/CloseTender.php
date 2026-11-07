<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CloseTender
{
    public function execute(Tender $tender, User $actor): Tender
    {
        return DB::transaction(function () use ($tender, $actor) {
            $locked = Tender::whereKey($tender->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $locked);
            if ($locked->status->value === 'closed') {
                return $locked;
            }
            abort_unless($locked->status->value === 'published', 409, 'Sólo se puede cerrar una licitación publicada.');
            $locked->update(['status' => 'closed']);
            TenderStatusHistory::create(['tender_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => 'published', 'to_status' => 'closed', 'reason' => 'Recepción cerrada para evaluación', 'occurred_at' => now()]);
            app(RecordTenderActivity::class)->execute($locked, $actor, 'tender.closed', ['status' => 'published'], ['status' => 'closed']);

            return $locked;
        });
    }
}
