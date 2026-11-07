<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Tenders\Models\Tender;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveTenderRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return $this->route('tender')
            ? Gate::allows('manage', $this->route('tender'))
            : Gate::allows('create', Tender::class);
    }

    public function rules(): array
    {
        $presence = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            ...$this->protectedFields(),
            'title' => [$presence, 'required', 'string', 'max:255'],
            'type' => [$presence, 'required', Rule::in(['products', 'services', 'works'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'currency' => ['sometimes', 'required', Rule::in(['ARS'])],
            'deadline_at' => [$presence, 'required', 'date', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/', 'after:now'],
            'conditions' => ['sometimes', 'nullable', 'array', 'max:100'],
            'source_system' => ['sometimes', 'nullable', 'string', 'max:255'],
            'external_reference' => ['sometimes', 'nullable', 'string', 'max:255'],
            'items' => ['missing'],
        ];
    }
}
