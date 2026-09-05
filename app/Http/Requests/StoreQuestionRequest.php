<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $question = $this->route('question');

        return $question
            ? Gate::allows('update', $question)
            : Gate::allows('create', \App\Models\Question::class);
    }

    public function rules(): array
    {
        $rules = [
            'subject_id' => 'required|exists:subjects,id',
            'question_text' => 'required|string|min:10|max:2000',
            'question_type' => ['required', Rule::in(['mcq_single', 'mcq_multiple', 'true_false', 'fill_blank'])],
            'points' => 'required|integer|min:1|max:10',
            'explanation' => 'nullable|string|max:1000',
        ];

        switch ($this->input('question_type')) {
            case 'mcq_single':
                $rules['options'] = 'required|array|min:2|max:6';
                $rules['options.*'] = 'required|string|max:500';
                $rules['correct_answers'] = 'required|array|size:1';
                $rules['correct_answers.*'] = 'required|string|in:A,B,C,D,E,F';
                break;

            case 'mcq_multiple':
                $rules['options'] = 'required|array|min:2|max:6';
                $rules['options.*'] = 'required|string|max:500';
                $rules['correct_answers'] = 'required|array|min:1';
                $rules['correct_answers.*'] = 'required|string|in:A,B,C,D,E,F';
                break;

            case 'true_false':
                $rules['correct_answers'] = 'required|array|size:1';
                $rules['correct_answers.*'] = 'required|string|in:true,false';
                break;

            case 'fill_blank':
                $rules['correct_answers'] = 'required|array|min:1|max:3';
                $rules['correct_answers.*'] = 'required|string|max:200';
                break;
        }

        return $rules;
    }
}
