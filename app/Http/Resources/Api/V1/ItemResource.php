<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'tender_id' => $this->tender_id,
            'position' => $this->position, 'description' => $this->description,
            'unit' => $this->unit, 'quantity' => $this->quantity,
            'attributes' => $this->attributes, 'reference_code' => $this->reference_code, 'notes' => $this->notes,
        ];
    }
}
