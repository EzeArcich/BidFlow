<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'type' => $this->type->value,
            'legal_name' => $this->legal_name, 'trade_name' => $this->trade_name, 'is_demo' => $this->is_demo,
            'capabilities' => $this->whenLoaded('capabilities', fn () => $this->capabilities->pluck('capability')),
            'contacts' => $this->whenLoaded('contacts', fn () => $this->contacts->map(fn ($contact) => [
                'id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email,
            ])),
        ];
    }
}
