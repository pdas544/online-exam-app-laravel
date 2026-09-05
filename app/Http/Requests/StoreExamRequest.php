<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $exam = $this->route('exam');

        return $exam
            ? Gate::allows('update', $exam)
            : Gate::allows('create', \App\Models\Exam::class);
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'instructions_file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:5120',
            'subject_id' => 'required|exists:subjects,id',
            'academic_year' => 'required|integer|min:2000|max:'.date('Y'),
            'semester' => 'required|in:1,2,3,4,5,6,7,8',
            'time_limit' => 'required|integer|min:5|max:480',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'available_from' => 'nullable|date',
            'available_to' => 'nullable|date|after:available_from',
            'passing_marks' => 'required|integer|min:0',
            'max_attempts' => 'required|integer|min:1|max:10',
            'status' => 'required|in:draft,published,archived',
        ];
    }
}
