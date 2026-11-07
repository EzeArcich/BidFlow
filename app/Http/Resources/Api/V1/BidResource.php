<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BidResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'tender_id' => $this->tender_id, 'supplier_id' => $this->supplier_id, 'invitation_id' => $this->invitation_id, 'status' => $this->status->value,
            'currency' => $this->currency, 'total_amount' => $this->total_amount, 'conditions' => $this->conditions, 'warranty' => $this->warranty,
            'submitted_at' => $this->submitted_at?->toIso8601String(), 'submitted_snapshot' => $this->submitted_snapshot,
            'lines' => $this->whenLoaded('items', fn () => $this->items->map(fn ($l) => $l->only(['id', 'tender_item_id', 'description', 'quantity', 'unit_price', 'subtotal', 'delivery_days', 'conditions', 'notes']))),
        ];
    }
}
