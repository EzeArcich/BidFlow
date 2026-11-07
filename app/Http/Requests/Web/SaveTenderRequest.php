<?php

namespace App\Http\Requests\Web;

use Carbon\CarbonImmutable;

class SaveTenderRequest extends \App\Http\Requests\Api\V1\SaveTenderRequest
{
    protected function prepareForValidation(): void
    {
        $local = $this->input('deadline_local');
        if (is_string($local)) {
            try {
                $date = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $local, config('bidding.display_timezone'));
                $this->merge(['deadline_at' => $date && $date->format('Y-m-d\TH:i') === $local ? $date->utc()->toIso8601String() : $local]);
            } catch (\Throwable) {
                $this->merge(['deadline_at' => $local]);
            }
        }
        if ($this->has('conditions_text')) {
            $existing = $this->route('tender')?->conditions ?? [];
            $existing['notes'] = $this->input('conditions_text');
            $this->merge(['conditions' => $existing]);
        }
    }

    public function rules(): array
    {
        return [...parent::rules(), 'deadline_local' => ['required', 'string'], 'conditions_text' => ['nullable', 'string', 'max:10000']];
    }

    public function domainData(): array
    {
        return $this->safe()->except(['deadline_local', 'conditions_text']);
    }
}
