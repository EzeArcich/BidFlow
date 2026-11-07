<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'number' => $this->number, 'award_id' => $this->award_id, 'tender_id' => $this->tender_id, 'supplier_id' => $this->supplier_id, 'status' => $this->status->value,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => $l->only(['id', 'award_line_id', 'quantity', 'unit_price', 'subtotal', 'currency', 'snapshot']))),
            'total_amount' => $this->when($this->relationLoaded('lines'), fn () => $this->lines->reduce(fn ($t, $l) => bcadd($t, $l->subtotal, 2), '0.00'))];
    }
}
