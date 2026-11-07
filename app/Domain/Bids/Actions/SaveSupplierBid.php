<?php

namespace App\Domain\Bids\Actions;

use App\Domain\Bids\Models\Bid;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Integrations\Models\AuditLog;
use App\Domain\Shared\Decimal;
use App\Domain\Suppliers\Models\SupplierUser;
use App\Domain\Suppliers\Queries\PortalAccess;
use App\Domain\Tenders\Models\Tender;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SaveSupplierBid
{
    public function __construct(private PortalAccess $access) {}

    public function execute(SupplierUser $actor, string $invitationId, array $data = [], bool $submit = false): Bid
    {
        $invitation = $this->access->invitation($actor, $invitationId);

        return DB::transaction(function () use ($actor, $invitationId, $invitation, $data, $submit): Bid {
            $tender = Tender::whereKey($invitation->tender_id)->lockForUpdate()->firstOrFail();
            $invitation = $this->access->invitation($actor, $invitationId, true);
            $bid = Bid::where('invitation_id', $invitationId)->where('supplier_id', $actor->supplier_id)->lockForUpdate()->first();
            if ($submit && $bid?->status->value === 'submitted') {
                return $bid->load('items');
            }
            abort_unless($tender->status->value === 'published' && $tender->deadline_at->isFuture() && $invitation->expires_at->isFuture()
             && in_array($invitation->status->value, ['pending', 'sent', 'viewed', 'responded']), 409, 'La recepción de ofertas no está disponible.');
            abort_if($bid && $bid->status->value !== 'draft', 409, 'La oferta presentada no puede modificarse.');
            if ($bid) {
                Gate::forUser($actor)->authorize('update', $bid);
            }
            if ($submit) {
                abort_unless($bid && $bid->items()->exists(), 422, 'Guardá una oferta con al menos una línea antes de presentarla.');
                $bid->load('items');
                $snapshot = ['supplier_id' => $actor->supplier_id, 'supplier_user_id' => $actor->id, 'currency' => $bid->currency, 'total_amount' => $bid->total_amount,
                    'conditions' => $bid->conditions, 'warranty' => $bid->warranty, 'submitted_at' => now()->toIso8601String(),
                    'lines' => $bid->items->map(fn ($line) => $line->only(['id', 'tender_item_id', 'description', 'quantity', 'unit_price', 'subtotal', 'delivery_days', 'notes', 'conditions']))->all()];
                $bid->update(['status' => 'submitted', 'submitted_at' => now(), 'submitted_snapshot' => $snapshot]);
                $invitation->update(['status' => 'responded', 'responded_at' => now()]);
            } else {
                $total = '0.00';
                $prepared = [];
                foreach ($data['lines'] as $index => $line) {
                    $item = $tender->items()->whereKey($line['tender_item_id'])->lockForUpdate()->first();
                    if (! $item || bccomp($line['quantity'], $item->quantity, 3) > 0) {
                        throw ValidationException::withMessages(["lines.{$index}.quantity" => 'La cantidad ofertada debe corresponder a un ítem propio y no superar la solicitada.']);
                    }
                    $subtotal = Decimal::subtotal($line['quantity'], $line['unit_price']);
                    $total = bcadd($total, $subtotal, 2);
                    Decimal::amount($total);
                    $prepared[] = [...$line, 'tender_id' => $tender->id, 'description' => $item->description, 'subtotal' => $subtotal];
                }
                $bid ??= Bid::create(['tender_id' => $tender->id, 'supplier_id' => $actor->supplier_id, 'invitation_id' => $invitation->id, 'status' => 'draft', 'currency' => $tender->currency, 'total_amount' => '0.00']);
                $bid->items()->delete();
                foreach ($prepared as $line) {
                    BidItem::create([...$line, 'bid_id' => $bid->id]);
                }
                $bid->update(['total_amount' => $total, 'conditions' => $data['conditions'] ?? null, 'warranty' => $data['warranty'] ?? null]);
            }
            AuditLog::create(['actor_id' => null, 'action' => $submit ? 'bid.submitted' : 'bid.draft_saved', 'entity_type' => 'bid', 'entity_id' => $bid->id,
                'after' => ['supplier_user_id' => $actor->id, 'supplier_id' => $actor->supplier_id, 'total_amount' => $bid->total_amount], 'origin' => 'portal', 'occurred_at' => now()]);

            return $bid->load('items');
        });
    }
}
