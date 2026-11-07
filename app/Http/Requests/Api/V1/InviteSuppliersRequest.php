<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class InviteSuppliersRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('tender'));
    }

    public function rules(): array
    {
        return [
            ...$this->protectedFields(),
            'invitations' => ['required', 'array', 'min:1', 'max:100'],
            'invitations.*' => ['required', 'array:supplier_id,contact_id'],
            'invitations.*.supplier_id' => ['required', 'ulid', 'distinct'],
            'invitations.*.contact_id' => ['sometimes', 'nullable', 'ulid'],
        ];
    }
}
