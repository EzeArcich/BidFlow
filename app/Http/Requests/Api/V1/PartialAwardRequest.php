<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Support\Facades\Gate;

class PartialAwardRequest extends ApiRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', $this->route('tender'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->is('api/*') && $this->header('Idempotency-Key')) {
            $this->merge(['request_key' => $this->header('Idempotency-Key')]);
        }
        if (! $this->is('api/*') && is_array($this->input('lines'))) {
            $lines = array_values(array_filter($this->input('lines'), fn ($l) => is_array($l) && ($l['select'] ?? null) === '1'));
            foreach ($lines as &$line) {
                unset($line['select']);
            }unset($line);
            $this->merge(['lines' => $lines]);
        }
    }

    public function rules(): array
    {
        return [
            ...$this->protectedFields(), 'request_key' => ['required', 'string', 'max:100'], 'reason' => ['required', 'string', 'max:10000'],
            'lines' => ['required', 'array', 'min:1', 'max:1000'], 'lines.*' => ['array:bid_item_id,quantity,unit_price,notes'],
            'lines.*.bid_item_id' => ['required', 'ulid', 'distinct'],
            'lines.*.quantity' => ['bail', 'required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,3})?$/', function ($a, $v, $f) {
                if (bccomp($v, '0', 3) <= 0) {
                    $f('La cantidad debe ser positiva.');
                }
            }],
            'lines.*.unit_price' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'], 'lines.*.notes' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
