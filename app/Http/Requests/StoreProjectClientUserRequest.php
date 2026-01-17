<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectClientUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && in_array($user->role, Role::adminPanelRoles(), true);
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $customerId = $customer?->id;

        $projectRule = Rule::exists('projects', 'id');
        if ($customerId) {
            $projectRule = $projectRule->where('customer_id', $customerId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'project_id' => ['required', $projectRule],
        ];
    }
}
