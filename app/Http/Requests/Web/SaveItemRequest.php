<?php

namespace App\Http\Requests\Web;

class SaveItemRequest extends \App\Http\Requests\Api\V1\SaveItemRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('specifications_text')) {
            $item = $this->route('item');
            $existing = is_string($item) ? $this->route('tender')->items()->whereKey($item)->first()?->attributes ?? [] : [];
            $existing['specifications'] = $this->input('specifications_text');
            $this->merge(['attributes' => $existing]);
        }
    }

    public function rules(): array
    {
        return [...parent::rules(), 'specifications_text' => ['nullable', 'string', 'max:10000']];
    }

    public function domainData(): array
    {
        return $this->safe()->except('specifications_text');
    }
}
