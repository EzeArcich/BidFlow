<?php

namespace App\Domain\Tenders\Queries;

use App\Domain\Awards\Models\AwardLine;
use App\Domain\Tenders\Models\Tender;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class EvaluateTender
{
    public function execute(Tender $tender, User $actor): array
    {
        Gate::forUser($actor)->authorize('view', $tender);
        $tender->load(['items' => fn ($q) => $q->orderBy('position'), 'bids' => fn ($q) => $q->whereIn('status', ['submitted', 'accepted'])->with(['items', 'supplier']), 'awards.lines', 'workOrders.lines']);
        $allocated = AwardLine::where('tender_id', $tender->id)->get()->groupBy('tender_item_id');
        $consumed = AwardLine::where('tender_id', $tender->id)->get()->groupBy('bid_item_id');
        $items = $tender->items->map(function ($item) use ($tender, $allocated, $consumed) {
            $awarded = ($allocated[$item->id] ?? collect())->reduce(fn ($total, $l) => bcadd($total, $l->quantity, 3), '0.000');
            $quotes = collect();
            foreach ($tender->bids as $bid) {
                foreach ($bid->items as $line) {
                    if ($line->tender_item_id === $item->id) {
                        $used = ($consumed[$line->id] ?? collect())->reduce(fn ($total, $l) => bcadd($total, $l->quantity, 3), '0.000');
                        $quotes->push(['bid_item_id' => $line->id, 'bid_id' => $bid->id, 'supplier_id' => $bid->supplier_id, 'supplier_name' => $bid->supplier->trade_name,
                            'quantity' => $line->quantity, 'unit_price' => $line->unit_price, 'subtotal' => $line->subtotal, 'delivery_days' => $line->delivery_days,
                            'conditions' => $line->conditions, 'notes' => $line->notes, 'awarded_quantity' => $used, 'available_quantity' => bcsub($line->quantity, $used, 3)]);
                    }
                }
            }

            return ['id' => $item->id, 'description' => $item->description, 'unit' => $item->unit, 'requested_quantity' => $item->quantity, 'awarded_quantity' => $awarded, 'pending_quantity' => bcsub($item->quantity, $awarded, 3), 'quotes' => $quotes->all()];
        });

        return ['tender_id' => $tender->id, 'status' => $tender->status->value, 'currency' => $tender->currency, 'items' => $items->all(),
            'bids' => $tender->bids->map(fn ($bid) => ['id' => $bid->id, 'supplier_id' => $bid->supplier_id, 'supplier_name' => $bid->supplier->trade_name, 'total_amount' => $bid->total_amount, 'quoted_items' => $bid->items->count(), 'partial' => $bid->items->count() < $tender->items->count() || $bid->items->contains(fn ($line) => bccomp($line->quantity, $tender->items->firstWhere('id', $line->tender_item_id)->quantity, 3) < 0)])->all()];
    }
}
