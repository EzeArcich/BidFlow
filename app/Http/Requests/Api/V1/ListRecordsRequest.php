<?php

namespace App\Http\Requests\Api\V1;

use App\Domain\Shared\Enums\TenderStatus;
use App\Domain\Shared\Enums\TenderType;
use Illuminate\Validation\Rule;

class ListRecordsRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'string', 'max:200'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::enum(TenderStatus::class)],
            'type' => ['sometimes', Rule::enum(TenderType::class)],
        ];
    }
}
