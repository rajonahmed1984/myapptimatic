<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        return view('admin.customers.index', [
            'customers' => Customer::query()
                ->withCount('subscriptions')
                ->withCount(['subscriptions as active_subscriptions_count' => function ($query) {
                    $query->where('status', 'active');
                }])
                ->latest()
                ->get(),
        ]);
    }

    public function create()
    {
        return view('admin.customers.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'access_override_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'user_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'user_password' => ['nullable', 'string', 'min:8'],
        ]);

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'access_override_until' => $data['access_override_until'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        if (! empty($data['user_email']) && ! empty($data['user_password'])) {
            User::create([
                'name' => $data['user_name'] ?: $customer->name,
                'email' => $data['user_email'],
                'password' => Hash::make($data['user_password']),
                'role' => 'client',
                'customer_id' => $customer->id,
            ]);
        }

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer created.');
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', [
            'customer' => $customer,
        ]);
    }

    public function show(Customer $customer)
    {
        $customer->load([
            'subscriptions.plan.product',
            'invoices' => function ($query) {
                $query->latest('issue_date');
            },
        ]);

        return view('admin.customers.show', [
            'customer' => $customer,
        ]);
    }

    public function update(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'access_override_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $customer->update($data);

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        User::where('customer_id', $customer->id)->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer deleted.');
    }
}
