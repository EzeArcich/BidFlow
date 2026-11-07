<?php

namespace App\Domain\Tenders\Actions;

use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierContact;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderInvitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class InviteSuppliers
{
    public function __construct(private WithDraftTender $draft, private RecordTenderActivity $audit) {}

    public function execute(Tender $tender, User $actor, array $invitations): Collection
    {
        return $this->draft->execute($tender, $actor, function (Tender $locked) use ($actor, $invitations): Collection {
            if ($locked->deadline_at->isPast()) {
                throw ValidationException::withMessages(['deadline_at' => 'Set a future deadline before inviting suppliers.']);
            }
            $result = collect();
            foreach (collect($invitations)->sortBy('supplier_id') as $index => $data) {
                $supplierId = $data['supplier_id'];
                $link = OrganizationSupplier::where('supplier_id', $supplierId)->where('status', 'active')->lockForUpdate()->first();
                $supplier = $link ? Supplier::whereKey($supplierId)->where('status', 'active')->lockForUpdate()->first() : null;
                if (! $supplier) {
                    throw ValidationException::withMessages(["invitations.{$index}.supplier_id" => 'Select an active supplier linked to this organization.']);
                }
                $contactId = $data['contact_id'] ?? null;
                if ($contactId && ! SupplierContact::where('supplier_id', $supplierId)->whereKey($contactId)->exists()) {
                    throw ValidationException::withMessages(["invitations.{$index}.contact_id" => 'Select a contact belonging to this supplier.']);
                }
                // Parent lock serializes duplicate submissions; UNIQUE remains the DB barrier.
                $invitation = $locked->invitations()->where('supplier_id', $supplierId)->first();
                if (! $invitation) {
                    $invitation = TenderInvitation::create([
                        'tender_id' => $locked->id, 'supplier_id' => $supplierId,
                        'contact_id' => $contactId, 'status' => 'pending', 'expires_at' => $locked->deadline_at,
                    ]);
                    $this->audit->execute($locked, $actor, 'tender.supplier_invited', null, ['invitation_id' => $invitation->id, 'supplier_id' => $supplierId]);
                }
                $result->push($invitation);
            }

            return $result;
        });
    }
}
