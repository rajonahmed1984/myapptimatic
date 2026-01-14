<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    private const STATUSES = ['pending', 'in_progress', 'blocked', 'completed', 'done'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(self::STATUSES)],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ];
    }
}
