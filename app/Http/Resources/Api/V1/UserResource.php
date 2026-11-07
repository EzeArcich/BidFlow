<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'organizations' => $this->whenLoaded('organizations', fn () => $this->organizations->map(fn ($organization) => [
                'id' => $organization->id, 'name' => $organization->name,
                'role' => $organization->pivot->role, 'status' => $organization->status,
            ])),
        ];
    }
}
