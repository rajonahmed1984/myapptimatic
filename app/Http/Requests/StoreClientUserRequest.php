<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Role;

class StoreClientUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, Role::adminPanelRoles(), true);
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'access_override_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'user_password' => ['nullable', 'string', 'min:8'],
            'send_account_message' => ['nullable', 'boolean'],
            'default_sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
            'avatar' => ['prohibited'],
            'nid_file' => ['prohibited'],
            'cv_file' => ['prohibited'],
        ];

        if ($this->filled('user_password')) {
            $rules['email'][] = 'required';
            $rules['email'][] = Rule::unique('users', 'email');
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->filled('user_password') && ! $this->filled('email')) {
                $validator->errors()->add('email', 'Email is required to create login.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status must be active or inactive.',
        ];
    }
}
