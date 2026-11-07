<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class WithDraftTender
{
    public function execute(Tender $tender, User $actor, Closure $callback): mixed
    {
        return DB::transaction(function () use ($tender, $actor, $callback) {
            $locked = Tender::whereKey($tender->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $locked);
            abort_unless($locked->status === TenderStatus::Draft, 409, 'Only draft tenders can be modified or published.');

            return $callback($locked);
        });
    }
}
