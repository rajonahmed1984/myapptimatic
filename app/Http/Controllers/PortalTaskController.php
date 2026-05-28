<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalTaskController extends Controller
{
    private function authUserId(Request $request): int
    {
        $user = $request->user();

        return $user ? (int) $user->getAuthIdentifier() : 0;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $this->authUserId($request);
        if ($userId <= 0) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'include_clients' => ['nullable', 'boolean'],
        ]);

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $tasks = Task::query()
            ->with('customer:id,name,company_name')
            ->where('user_id', $userId)
            ->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
                $query->whereBetween('due_date', [$startDate, $endDate]);
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if (! $request->boolean('include_clients')) {
            return response()->json($tasks);
        }

        $clients = $this->availableClientsForUser($user);

        return response()->json([
            'tasks' => $tasks,
            'clients' => $clients,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $this->authUserId($request);
        if ($userId <= 0) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['required', 'date'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
        ]);

        $task = Task::create([
            'user_id' => $userId,
            'customer_id' => $data['customer_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'],
            'is_completed' => false,
        ]);

        return response()->json($task, 201);
    }

    public function update(Request $request, Task $task): JsonResponse
    {
        $userId = $this->authUserId($request);
        if ($userId <= 0) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $task = Task::query()->where('user_id', $userId)->find($task->id);
        if (! $task) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['sometimes', 'required', 'date'],
            'is_completed' => ['sometimes', 'required', 'boolean'],
            'customer_id' => ['sometimes', 'nullable', 'integer', 'exists:customers,id'],
        ]);

        $task->update($data);

        return response()->json($task);
    }

    public function destroy(Request $request, Task $task): JsonResponse
    {
        $userId = $this->authUserId($request);
        if ($userId <= 0) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $task = Task::query()->where('user_id', $userId)->find($task->id);
        if (! $task) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $task->delete();

        return response()->json(['success' => true]);
    }

    private function availableClientsForUser($user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isAdmin() || $user->isSupport()) {
            return Customer::query()
                ->select(['id', 'name', 'company_name'])
                ->orderBy('name')
                ->limit(300)
                ->get()
                ->map(fn (Customer $customer) => [
                    'id' => (int) $customer->id,
                    'name' => $customer->display_name,
                ])
                ->values()
                ->all();
        }

        if (! empty($user->customer_id)) {
            $customer = Customer::query()->find($user->customer_id);
            if ($customer) {
                return [[
                    'id' => (int) $customer->id,
                    'name' => $customer->display_name,
                ]];
            }
        }

        return [];
    }
}
