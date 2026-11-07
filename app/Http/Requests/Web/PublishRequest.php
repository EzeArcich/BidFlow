<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class PublishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('publish', $this->route('tender'));
    }

    public function rules(): array
    {
        return ['confirmation' => ['accepted']];
    }

    public function messages(): array
    {
        return ['confirmation.accepted' => 'Confirmá que revisaste los datos antes de publicar.'];
    }
}
