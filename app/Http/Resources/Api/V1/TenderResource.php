<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'organization_id' => $this->organization_id,
            'type' => $this->type->value, 'status' => $this->status->value,
            'title' => $this->title, 'description' => $this->description,
            'currency' => $this->currency, 'deadline_at' => $this->deadline_at->toIso8601String(),
            'conditions' => $this->conditions, 'source_system' => $this->source_system,
            'external_reference' => $this->external_reference, 'created_by' => $this->created_by,
            'is_demo' => $this->is_demo, 'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'items' => ItemResource::collection($this->whenLoaded('items')),
            'invitations' => InvitationResource::collection($this->whenLoaded('invitations')),
        ];
    }
}
