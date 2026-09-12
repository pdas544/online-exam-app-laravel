<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SyncTimerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'remaining_time' => 'required|integer|min:0',
        ];
    }
}
