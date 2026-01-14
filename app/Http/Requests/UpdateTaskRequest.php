<?php

namespace App\Http\Requests;

use App\Support\TaskSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    private const STATUSES = ['pending', 'in_progress', 'blocked', 'completed', 'done'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskTypeOptions = array_keys(TaskSettings::taskTypeOptions());
        $priorityOptions = array_keys(TaskSettings::priorityOptions());

        return [
            'status' => ['required', Rule::in(self::STATUSES)],
            'description' => ['nullable', 'string'],
            'task_type' => ['nullable', Rule::in($taskTypeOptions)],
            'priority' => ['nullable', Rule::in($priorityOptions)],
            'time_estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'string'],
            'relationship_ids' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'customer_visible' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['nullable', 'string'],
        ];
    }
}
