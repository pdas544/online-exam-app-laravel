<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateQuestionPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('exam'));
    }

    public function rules(): array
    {
        return [
            'points' => 'required|integer|min:1|max:10',
        ];
    }
}
