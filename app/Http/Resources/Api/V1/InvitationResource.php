<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'tender_id' => $this->tender_id,
            'supplier_id' => $this->supplier_id, 'contact_id' => $this->contact_id,
            'status' => $this->status->value, 'expires_at' => $this->expires_at->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
        ];
    }
}
