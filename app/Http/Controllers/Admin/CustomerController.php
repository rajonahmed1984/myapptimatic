<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\SystemLog;
use App\Models\SalesRepresentative;
use App\Models\Employee;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Setting;
use App\Http\Requests\StoreClientUserRequest;
use App\Enums\Role;
use App\Support\Branding;
use App\Support\SystemLogger;
use App\Support\UrlResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::query()
            ->with(['users:id,customer_id,role'])
            ->withCount('subscriptions')
            ->withCount(['subscriptions as active_subscriptions_count' => function ($query) {
                $query->where('status', 'active');
            }])
            ->withCount('projects')
            ->latest()
            ->paginate(25);

        $loginStatuses = $this->resolveCustomerLoginStatuses($customers);

        return view('admin.customers.index', compact('customers', 'loginStatuses'));
    }

    public function create()
    {
        return view('admin.customers.create', [
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function store(StoreClientUserRequest $request)
    {
        $data = $request->validated();

        $customer = Customer::create([
            'name' => $data['name'],
            'company_name' => $data['company_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => $data['status'],
            'access_override_until' => $data['access_override_until'] ?? null,
            'notes' => $data['notes'] ?? null,
            'default_sales_rep_id' => $data['default_sales_rep_id'] ?? null,
        ]);

        $uploadPaths = $this->handleUploads($request, $customer);
        if (! empty($uploadPaths)) {
            $customer->update($uploadPaths);
        }

        if (! empty($data['user_password'])) {
            if (empty($customer->email)) {
                return redirect()->route('admin.customers.create')
                    ->withErrors(['email' => 'Email is required to create login.'])
                    ->withInput();
            }

            User::create([
                'name' => $customer->name,
                'email' => $customer->email,
                'password' => Hash::make($data['user_password']),
                'role' => Role::CLIENT,
                'customer_id' => $customer->id,
            ]);

            if ($request->boolean('send_account_message')) {
                $this->sendAccountMessage($customer);
            }
        }

        SystemLogger::write('activity', 'Customer created.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer created.');
    }

    private function sendAccountMessage(Customer $customer): void
    {
        if (! $customer->email) {
            return;
        }

        $template = EmailTemplate::query()
            ->where('key', 'client_signup_email')
            ->first();

        $companyName = Setting::getValue('company_name', config('app.name'));
        $loginUrl = UrlResolver::portalUrl().'/login';

        $subject = $template?->subject ?: "Welcome to {$companyName}";
        $body = $template?->body ?: "Hi {{client_name}},\n\nYour account for {{company_name}} is ready. You can sign in here: {{login_url}}.\n\nThank you,\n{{company_name}}";
        $fromEmail = trim((string) ($template?->from_email ?? ''));

        $replacements = [
            '{{client_name}}' => $customer->name,
            '{{company_name}}' => $companyName,
            '{{login_url}}' => $loginUrl,
            '{{client_email}}' => $customer->email,
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);
        $bodyHtml = $this->formatEmailBody($body);
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();
        $portalLoginUrl = UrlResolver::portalUrl().'/login';

        try {
            Mail::send('emails.generic', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $portalLoginUrl,
                'portalLoginLabel' => 'log in to the client area',
                'bodyHtml' => new HtmlString($bodyHtml),
            ], function ($message) use ($customer, $subject, $fromEmail, $companyName) {
                $message->to($customer->email)
                    ->subject($subject);
                if ($fromEmail !== '') {
                    $message->from($fromEmail, $companyName);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to send account info email.', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function formatEmailBody(string $body): string
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            return '';
        }

        $looksLikeHtml = Str::contains($trimmed, ['<p', '<br', '<div', '<table', '<a ', '<strong', '<em', '<ul', '<ol', '<li']);

        if ($looksLikeHtml) {
            return $trimmed;
        }

        return nl2br(e($trimmed));
    }

    public function edit(Customer $customer)
    {
        return view('admin.customers.edit', [
            'customer' => $customer,
            'salesReps' => SalesRepresentative::orderBy('name')->get(['id', 'name', 'status']),
        ]);
    }

    public function show(Request $request, Customer $customer)
    {
        $tab = $request->query('tab', 'summary');
        $allowedTabs = ['summary', 'services', 'projects', 'invoices', 'tickets', 'emails', 'log'];

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'summary';
        }

        $customer->load([
            'subscriptions.plan.product',
            'subscriptions.latestOrder',
            'projects',
            'invoices' => function ($query) {
                $query->latest('issue_date');
            },
            'supportTickets' => function ($query) {
                $query->latest('updated_at');
            },
        ]);

        $activityLogs = collect();
        $emailLogs = collect();
        if ($tab === 'log') {
            $userIds = $customer->users()->pluck('id');
            $activityLogs = SystemLog::query()
                ->where(function ($query) use ($customer, $userIds) {
                    $query->where('context->customer_id', $customer->id);
                    if ($userIds->isNotEmpty()) {
                        $query->orWhereIn('user_id', $userIds);
                    }
                })
                ->latest()
                ->take(200)
                ->get();
        }
        if ($tab === 'emails' && $customer->email) {
            $emailLogs = SystemLog::query()
                ->where('category', 'email')
                ->whereJsonContains('context->to', strtolower($customer->email))
                ->latest()
                ->take(200)
                ->get();
        }

        return view('admin.customers.show', [
            'customer' => $customer,
            'tab' => $tab,
            'activityLogs' => $activityLogs,
            'emailLogs' => $emailLogs,
        ]);
    }

    public function impersonate(Request $request, Customer $customer)
    {
        // Prevent chaining impersonations
        if ($request->session()->has('impersonator_id')) {
            return back()->withErrors(['impersonate' => 'You are already impersonating another account. Stop impersonation first.']);
        }

        $user = $customer->users()
            ->where('role', 'client')
            ->orderBy('id')
            ->first();

        if (! $user) {
            return back()->withErrors(['impersonate' => 'No client login exists for this customer.']);
        }

        $request->session()->put('impersonator_id', $request->user()->id);
        Auth::login($user);
        // Enable employee-guard access when impersonating an employee account (same provider).
        if (Employee::where('user_id', $user->id)->exists()) {
            Auth::guard('employee')->login($user);
        } else {
            Auth::guard('employee')->logout();
        }
        $request->session()->regenerate();

        return redirect()->to($this->impersonationRedirectPath($user));
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
            'default_sales_rep_id' => ['nullable', 'exists:sales_representatives,id'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'nid_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
            'cv_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $customer->update(collect($data)->except(['avatar', 'nid_file', 'cv_file'])->all());

        $uploadPaths = $this->handleUploads($request, $customer);
        if (! empty($uploadPaths)) {
            $customer->update($uploadPaths);
        }

        SystemLogger::write('activity', 'Customer updated.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], $request->user()?->id, $request->ip());

        return redirect()->route('admin.customers.edit', $customer)
            ->with('status', 'Customer updated.');
    }

    public function destroy(Customer $customer)
    {
        SystemLogger::write('activity', 'Customer deleted.', [
            'customer_id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
        ], auth()->id(), request()->ip());

        User::where('customer_id', $customer->id)->delete();
        $customer->delete();

        return redirect()->route('admin.customers.index')
            ->with('status', 'Customer deleted.');
    }

    /**
     * Decide where to send an impersonated session based on the target user's roles.
     * Priority: active sales rep -> employee -> client dashboard.
     */
    private function impersonationRedirectPath(User $user): string
    {
        $rep = SalesRepresentative::query()
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($rep) {
            return route('rep.dashboard');
        }

        $isEmployee = Employee::query()->where('user_id', $user->id)->exists();

        if ($isEmployee) {
            return route('employee.dashboard');
        }

        return route('client.dashboard');
    }

    private function handleUploads(Request $request, Customer $customer): array
    {
        $paths = [];

        if ($request->hasFile('avatar')) {
            $paths['avatar_path'] = $request->file('avatar')
                ->store('avatars/customers/' . $customer->id, 'public');
        }

        if ($request->hasFile('nid_file')) {
            $paths['nid_path'] = $request->file('nid_file')
                ->store('nid/customers/' . $customer->id, 'public');
        }

        if ($request->hasFile('cv_file')) {
            $paths['cv_path'] = $request->file('cv_file')
                ->store('cv/customers/' . $customer->id, 'public');
        }

        return $paths;
    }

    private function resolveCustomerLoginStatuses($customers): array
    {
        $userIds = $customers->pluck('users')
            ->flatten()
            ->filter(fn ($user) => $user && $user->role === Role::CLIENT)
            ->pluck('id')
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return [];
        }

        $openSessions = UserSession::query()
            ->whereIn('user_id', $userIds)
            ->where('guard', 'web')
            ->whereNull('logout_at')
            ->orderByDesc('last_seen_at')
            ->get()
            ->groupBy('user_id');

        $threshold = now()->subMinutes(2);
        $statuses = [];

        foreach ($customers as $customer) {
            $status = 'logout';
            foreach ($customer->users->where('role', Role::CLIENT) as $user) {
                $session = $openSessions->get($user->id)?->first();
                if (! $session) {
                    continue;
                }

                $lastSeen = $session->last_seen_at;
                if ($lastSeen && $lastSeen->greaterThanOrEqualTo($threshold)) {
                    $status = 'login';
                    break;
                }

                $status = 'idle';
            }

            $statuses[$customer->id] = $status;
        }

        return $statuses;
    }
}
