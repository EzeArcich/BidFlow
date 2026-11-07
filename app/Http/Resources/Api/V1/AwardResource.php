<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AwardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'tender_id' => $this->tender_id, 'status' => $this->status, 'currency' => $this->currency, 'total_amount' => $this->total_amount, 'reason' => $this->reason, 'awarded_by' => $this->awarded_by, 'awarded_at' => $this->awarded_at->toIso8601String(), 'snapshot' => $this->snapshot,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => $l->only(['id', 'bid_id', 'bid_item_id', 'tender_item_id', 'supplier_id', 'quantity', 'unit_price', 'subtotal', 'currency', 'snapshot', 'notes']))),
            'orders' => WorkOrderResource::collection($this->whenLoaded('workOrders'))];
    }
}
