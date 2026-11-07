<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class PublishTender
{
    public function __construct(private WithDraftTender $draft, private RecordTenderActivity $audit) {}

    public function execute(Tender $tender, User $actor): Tender
    {
        return $this->draft->execute($tender, $actor, function (Tender $locked) use ($actor): Tender {
            $errors = [];
            if ($locked->deadline_at->isPast()) {
                $errors['deadline_at'] = 'Publication requires a future deadline.';
            }
            if (! $locked->items()->exists()) {
                $errors['items'] = 'Add at least one item before publication.';
            }
            $invitations = $locked->invitations()->orderBy('supplier_id')->lockForUpdate()->get();
            if ($invitations->isEmpty()) {
                $errors['invitations'] = 'Invite at least one supplier before publication.';
            }
            foreach ($invitations as $invitation) {
                $link = OrganizationSupplier::where('supplier_id', $invitation->supplier_id)->where('status', 'active')->lockForUpdate()->first();
                $supplier = $link ? Supplier::whereKey($invitation->supplier_id)->where('status', 'active')->lockForUpdate()->first() : null;
                if (! $supplier || $invitation->status->value !== 'pending' || $invitation->expires_at->isPast()) {
                    $errors['invitations'] = 'Every invitation must be pending, current, and linked to an active supplier.';
                }
            }
            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
            $locked->update(['status' => 'published']);
            TenderStatusHistory::create([
                'tender_id' => $locked->id, 'actor_id' => $actor->id,
                'from_status' => 'draft', 'to_status' => 'published', 'reason' => 'Tender published', 'occurred_at' => now(),
            ]);
            $this->audit->execute($locked, $actor, 'tender.published', ['status' => 'draft'], ['status' => 'published']);
            OutboxEvent::create([
                'event_type' => 'tender.published', 'resource_id' => $locked->id,
                'payload' => ['tender_id' => $locked->id], 'occurred_at' => now(),
            ]);

            return $locked;
        });
    }
}
