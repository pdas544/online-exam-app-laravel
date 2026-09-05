<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendWarningRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('forceEnd', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'message' => 'nullable|string|max:1000',
        ];
    }
}
