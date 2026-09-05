<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ReorderQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('exam'));
    }

    public function rules(): array
    {
        return [
            'questions' => 'required|array',
            'questions.*.id' => 'required|exists:questions,id',
            'questions.*.order' => 'required|integer|min:1',
        ];
    }
}
