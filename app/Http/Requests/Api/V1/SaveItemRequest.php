<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class SaveItemRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('tender'));
    }

    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            ...$this->protectedFields(),
            'description' => [$presence, 'required', 'string', 'max:10000'],
            'unit' => [$presence, 'required', 'string', 'max:50'],
            'quantity' => [
                'bail', $presence, 'required', 'string',
                'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,3})?$/',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (bccomp($value, '0', 3) <= 0) {
                        $fail('Quantity must be positive.');
                    }
                },
            ],
            'position' => ['sometimes', 'required', 'integer', 'min:1', 'max:2147483647'],
            'attributes' => ['sometimes', 'nullable', 'array', 'max:100'],
            'reference_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
        ];
    }
}
