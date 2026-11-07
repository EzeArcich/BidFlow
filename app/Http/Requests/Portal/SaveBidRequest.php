<?php

namespace App\Http\Requests\Portal;

use App\Domain\Suppliers\Models\SupplierUser;
use Illuminate\Foundation\Http\FormRequest;

class SaveBidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof SupplierUser;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->is('api/*') && is_array($this->input('lines'))) {
            $this->merge(['lines' => array_values(array_filter($this->input('lines'), fn ($line) => is_array($line) && ($line['quote'] ?? null) === '1'))]);
            $lines = $this->input('lines');
            foreach ($lines as &$line) {
                unset($line['quote']);
            } unset($line);
            $this->merge(['lines' => $lines]);
        }
    }

    public function rules(): array
    {
        return [
            'organization_id' => ['missing'], 'supplier_id' => ['missing'], 'status' => ['missing'], 'total_amount' => ['missing'],
            'currency' => ['sometimes', 'in:ARS'], 'conditions' => ['nullable', 'string', 'max:10000'], 'warranty' => ['nullable', 'string', 'max:10000'],
            'lines' => ['required', 'array', 'min:1', 'max:1000'], 'lines.*' => ['array:tender_item_id,quantity,unit_price,delivery_days,notes,conditions'],
            'lines.*.tender_item_id' => ['required', 'ulid', 'distinct'],
            'lines.*.quantity' => ['bail', 'required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,8})(?:\.[0-9]{1,3})?$/', function ($a, $v, $f) {
                if (bccomp($v, '0', 3) <= 0) {
                    $f('La cantidad debe ser positiva.');
                }
            }],
            'lines.*.unit_price' => ['required', 'string', 'regex:/^(?:0|[1-9][0-9]{0,12})(?:\.[0-9]{1,2})?$/'],
            'lines.*.delivery_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lines.*.notes' => ['nullable', 'string', 'max:10000'], 'lines.*.conditions' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
