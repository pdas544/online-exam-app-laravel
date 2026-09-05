<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SaveAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('view', $this->route('session'));
    }

    public function rules(): array
    {
        return [
            'question_id' => 'required|exists:questions,id',
            'answer' => 'nullable',
            'is_marked_for_review' => 'boolean',
        ];
    }
}
