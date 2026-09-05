<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class LogViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
        ];
    }
}
