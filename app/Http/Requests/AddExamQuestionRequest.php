<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class AddExamQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('exam'));
    }

    public function rules(): array
    {
        return [
            'question_id' => 'required|exists:questions,id',
            'points_override' => 'nullable|integer|min:1|max:10',
        ];
    }
}
