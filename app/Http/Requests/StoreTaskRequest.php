<?php

namespace App\Http\Requests;

use App\Support\TaskSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $taskTypeOptions = array_keys(TaskSettings::taskTypeOptions());
        $priorityOptions = array_keys(TaskSettings::priorityOptions());
        $maxMb = TaskSettings::uploadMaxMb();

        $routeName = (string) $this->route()?->getName();
        $isClient = str_starts_with($routeName, 'client.');

        $startDateRule = $isClient ? ['nullable', 'date'] : ['required', 'date'];
        $dueDateRule = $isClient
            ? ['nullable', 'date']
            : ['required', 'date', 'after_or_equal:start_date'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'descriptions' => ['nullable', 'array'],
            'descriptions.*' => ['nullable', 'string'],
            'task_type' => ['required', Rule::in($taskTypeOptions)],
            'priority' => ['nullable', Rule::in($priorityOptions)],
            'time_estimate_minutes' => ['nullable', 'integer', 'min:0'],
            'tags' => ['nullable', 'string'],
            'relationship_ids' => ['nullable', 'string'],
            'start_date' => $startDateRule,
            'due_date' => $dueDateRule,
            'assignees' => ['nullable', 'array'],
            'assignees.*' => ['nullable', 'string'],
            'assignee' => ['nullable', 'string'],
            'customer_visible' => ['nullable', 'boolean'],
            'attachment' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf,docx,xlsx',
                'max:' . ($maxMb * 1024),
            ],
        ];
    }
}
