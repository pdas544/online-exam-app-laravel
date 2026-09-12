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
            'type' => 'required|string|in:tab_switch,window_blur,copy_attempt,paste_attempt,fullscreen_exit,multiple_ips,time_manipulation,suspicious_activity,tab_key,new_tab_attempt,page_navigation,window_resize,window_minimize',
            'description' => 'required|string',
            'metadata' => 'nullable|array',
        ];
    }
}
