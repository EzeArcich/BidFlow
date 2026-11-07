<?php

namespace App\Domain\Awards\Actions;

use App\Domain\Awards\Models\Award;
use App\Domain\Awards\Models\AwardLine;
use App\Domain\Bids\Models\BidItem;
use App\Domain\Integrations\Models\OutboxEvent;
use App\Domain\Shared\Decimal;
use App\Domain\Suppliers\Models\OrganizationSupplier;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Tenders\Actions\RecordTenderActivity;
use App\Domain\Tenders\Models\Tender;
use App\Domain\Tenders\Models\TenderStatusHistory;
use App\Domain\WorkOrders\Models\WorkOrder;
use App\Domain\WorkOrders\Models\WorkOrderLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AllocateAward
{
    public function execute(Tender $tender, User $actor, array $data): Award
    {
        $canonical = ['actor_id' => $actor->id, 'reason' => $data['reason'], 'lines' => collect($data['lines'])->sortBy('bid_item_id')->values()->map(fn ($l) => ['bid_item_id' => $l['bid_item_id'], 'quantity' => bcadd($l['quantity'], '0', 3), 'unit_price' => bcadd($l['unit_price'], '0', 2), 'notes' => $l['notes'] ?? null])->all()];
        $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($tender, $actor, $data, $canonical, $hash): Award {
            $locked = Tender::whereKey($tender->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('manage', $locked);
            $existing = Award::where('tender_id', $locked->id)->where('request_key', $data['request_key'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'La clave de operación ya se usó con otros datos.');

                return $existing->load(['lines', 'workOrders.lines']);
            }
            abort_unless(in_array($locked->status->value, ['closed', 'partially_awarded']), 409, 'Cerrá la recepción de ofertas antes de adjudicar.');
            $prepared = [];
            $allocated = [];
            $total = '0.00';
            foreach ($canonical['lines'] as $index => $selection) {
                $line = BidItem::where('tender_id', $locked->id)->whereKey($selection['bid_item_id'])->lockForUpdate()->first();
                $bid = $line?->bid()->whereIn('status', ['submitted', 'accepted'])->first();
                if (! $bid) {
                    throw ValidationException::withMessages(["lines.{$index}" => 'La línea debe pertenecer a una oferta presentada de esta licitación.']);
                }
                $item = $locked->items()->whereKey($line->tender_item_id)->lockForUpdate()->firstOrFail();
                $supplier = Supplier::whereKey($bid->supplier_id)->where('status', 'active')->first();
                if (! $supplier || ! OrganizationSupplier::where('supplier_id', $bid->supplier_id)->where('status', 'active')->exists()) {
                    throw ValidationException::withMessages(["lines.{$index}" => 'El proveedor ya no está habilitado.']);
                }
                $used = AwardLine::where('bid_item_id', $line->id)->sum('quantity');
                $allocated[$item->id] ??= (string) AwardLine::where('tender_item_id', $item->id)->sum('quantity');
                $allocated[$item->id] = bcadd($allocated[$item->id], $selection['quantity'], 3);
                if (bccomp($allocated[$item->id], $item->quantity, 3) > 0 || bccomp(bcadd((string) $used, $selection['quantity'], 3), $line->quantity, 3) > 0) {
                    throw ValidationException::withMessages(["lines.{$index}.quantity" => 'La cantidad supera el pendiente solicitado o el disponible de esta oferta.']);
                }
                $subtotal = Decimal::subtotal($selection['quantity'], $selection['unit_price']);
                $total = bcadd($total, $subtotal, 2);
                Decimal::amount($total);
                $prepared[] = [...$selection, 'tender_id' => $locked->id, 'bid_id' => $bid->id, 'tender_item_id' => $item->id, 'supplier_id' => $bid->supplier_id, 'currency' => $locked->currency, 'subtotal' => $subtotal,
                    'snapshot' => ['description' => $item->description, 'unit' => $item->unit, 'supplier_name' => $supplier->trade_name, 'offered_quantity' => $line->quantity, 'offered_unit_price' => $line->unit_price, 'quantity' => $selection['quantity'], 'unit_price' => $selection['unit_price'], 'subtotal' => $subtotal, 'currency' => $locked->currency, 'delivery_days' => $line->delivery_days, 'conditions' => $line->conditions]];
            }
            $award = Award::create(['tender_id' => $locked->id, 'bid_id' => null, 'legacy' => false, 'awarded_by' => $actor->id, 'awarded_at' => now(), 'reason' => $data['reason'], 'status' => 'confirmed',
                'currency' => $locked->currency, 'total_amount' => $total, 'request_key' => $data['request_key'], 'request_hash' => $hash, 'snapshot' => ['currency' => $locked->currency, 'total_amount' => $total, 'reason' => $data['reason'], 'lines' => array_column($prepared, 'snapshot')]]);
            foreach ($prepared as $line) {
                AwardLine::create([...$line, 'award_id' => $award->id]);
            }
            $lines = $award->lines()->get();
            foreach ($lines->groupBy('supplier_id') as $supplierId => $supplierLines) {
                $order = WorkOrder::create(['award_id' => $award->id, 'tender_id' => $locked->id, 'supplier_id' => $supplierId, 'number' => 'BF-'.now()->year.'-'.$award->id.'-'.$supplierId, 'status' => 'issued']);
                foreach ($supplierLines as $line) {
                    WorkOrderLine::create(['work_order_id' => $order->id, 'award_id' => $award->id, 'supplier_id' => $supplierId, 'award_line_id' => $line->id, 'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'currency' => $line->currency, 'snapshot' => $line->snapshot]);
                }
                app(RecordTenderActivity::class)->execute($locked, $actor, 'work_order.created', null, ['work_order_id' => $order->id, 'supplier_id' => $supplierId, 'award_id' => $award->id]);
            }
            $complete = $locked->items()->get()->every(fn ($item) => bccomp((string) AwardLine::where('tender_item_id', $item->id)->sum('quantity'), $item->quantity, 3) === 0);
            $status = $complete ? 'awarded' : 'partially_awarded';
            $before = $locked->status->value;
            $locked->update(['status' => $status]);
            TenderStatusHistory::create(['tender_id' => $locked->id, 'actor_id' => $actor->id, 'from_status' => $before, 'to_status' => $status, 'reason' => $data['reason'], 'metadata' => ['award_id' => $award->id], 'occurred_at' => now()]);
            app(RecordTenderActivity::class)->execute($locked, $actor, 'tender.allocated', ['status' => $before], ['status' => $status, 'award_id' => $award->id, 'total_amount' => $total]);
            OutboxEvent::create(['event_type' => 'tender.allocated', 'resource_id' => $award->id, 'payload' => ['tender_id' => $locked->id, 'award_id' => $award->id, 'status' => $status], 'occurred_at' => now()]);

            return $award->load(['lines', 'workOrders.lines']);
        });
    }
}
