<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SaveTender
{
    public function __construct(private WithDraftTender $draft, private RecordTenderActivity $audit) {}

    public function create(User $actor, array $data): Tender
    {
        Gate::forUser($actor)->authorize('create', Tender::class);
        $data['deadline_at'] = CarbonImmutable::parse($data['deadline_at'])->utc();

        return DB::transaction(function () use ($actor, $data): Tender {
            $tender = Tender::create([...$data, 'created_by' => $actor->id, 'status' => TenderStatus::Draft]);
            TenderStatusHistory::create([
                'tender_id' => $tender->id, 'actor_id' => $actor->id,
                'to_status' => 'draft', 'reason' => 'Tender created', 'occurred_at' => now(),
            ]);
            $this->audit->execute($tender, $actor, 'tender.created', null, ['status' => 'draft']);

            return $tender;
        });
    }

    public function update(Tender $tender, User $actor, array $data): Tender
    {
        if (isset($data['deadline_at'])) {
            $data['deadline_at'] = CarbonImmutable::parse($data['deadline_at'])->utc();
        }

        return $this->draft->execute($tender, $actor, function (Tender $locked) use ($actor, $data): Tender {
            // Existing invitation deadlines follow the tender deadline, including extensions.
            if (array_key_exists('deadline_at', $data)) {
                $locked->invitations()->get()->each(fn ($invitation) => $invitation->update(['expires_at' => $data['deadline_at']]));
            }
            $before = $locked->only(array_keys($data));
            try {
                $locked->update($data);
            } catch (QueryException $exception) {
                if (isset($data['type']) && $exception->errorInfo[0] === '23503') {
                    abort(409, 'This classification has dependent records and cannot be changed.');
                }
                throw $exception;
            }
            $this->audit->execute($locked, $actor, 'tender.updated', $before, $locked->only(array_keys($data)));

            return $locked;
        });
    }
}
