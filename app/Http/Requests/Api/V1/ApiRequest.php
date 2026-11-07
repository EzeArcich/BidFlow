<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

abstract class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function protectedFields(): array
    {
        return array_fill_keys(['id', 'organization_id', 'created_by', 'status', 'is_demo', 'repair_scope', 'tender_id'], ['missing']);
    }
}
