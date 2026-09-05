<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BulkAddQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('exam'));
    }

    public function rules(): array
    {
        return [
            'question_ids' => 'required|array',
            'question_ids.*' => 'exists:questions,id',
        ];
    }
}
