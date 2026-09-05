<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        return $user
            ? Gate::allows('update', $user)
            : Gate::allows('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($userId)],
            'password' => $userId ? 'sometimes|nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,teacher',
            'department' => 'required_if:role,teacher|string|max:255',
            'designation' => 'required_if:role,teacher|string|max:255',
        ];
    }
}
