# Project-Specific User Restrictions

## Overview
This document summarizes all access restrictions implemented for Project-Specific Users (users with `isClientProject()` = true and a `project_id` assigned).

## What Project-Specific Users CAN Access

✅ **Project Details**
- View their assigned project via `/client/projects/{project_id}`
- See project overview, tasks, milestones, status
- View project description and specifications

✅ **Task Management**
- View all project tasks and subtasks
- Create, update, and manage tasks
- View task activity and history
- Upload task attachments
- Access task details and activity feeds

✅ **Communication**
- Project chat (real-time messaging)
- Task-level chat for discussions
- View and send messages
- Upload and view attachments

✅ **User Profile**
- Edit their own profile at `/client/profile`
- Update personal information

✅ **Support Tickets** (Optional - currently allowed)
- Access support ticket system
- Create and view support tickets

## What Project-Specific Users CANNOT Access

❌ **Dashboard**
- Cannot access `/client/dashboard`
- Redirected to their assigned project instead

❌ **Project Index**
- Cannot access `/client/projects` (list of all projects)
- Redirected to their assigned project instead

❌ **Financial Information**
- Cannot see project budget
- Cannot see project payments/transactions
- Cannot see overhead fees
- Cannot see project pricing details

❌ **Invoices**
- Cannot access `/client/invoices/*`
- Cannot view, pay, or download invoices
- All invoice routes blocked by middleware

❌ **Services & Subscriptions**
- Cannot access `/client/services/*`
- Cannot view subscription details
- Cannot see service pricing or renewals

❌ **Domains**
- Cannot access `/client/domains/*`
- Cannot view domain management

❌ **Licenses**
- Cannot access `/client/licenses/*`
- Cannot view license details

❌ **Orders**
- Cannot access `/client/orders/*`
- Cannot place or view orders

❌ **Affiliates**
- Cannot access `/client/affiliates/*`
- Cannot view commissions or referrals
- Cannot access affiliate program

❌ **Maintenance Information**
- Maintenance details hidden in project view
- Cannot see maintenance pricing or billing type

## Implementation Details

### 1. Authentication Flow
**File:** `app/Http/Controllers/AuthController.php`
```php
// Project-specific users redirect to their assigned project
if ($user->isClientProject() && $user->project_id) {
    return redirect()->route('client.projects.show', $user->project_id);
}
```

### 2. Dashboard Redirect
**File:** `app/Http/Controllers/Client/DashboardController.php`
```php
public function index(Request $request) {
    $user = $request->user();
    
    if ($user->isClientProject() && $user->project_id) {
        return redirect()->route('client.projects.show', $user->project_id);
    }
    // ... load dashboard data for regular clients
}
```

### 3. Project Index Redirect
**File:** `app/Http/Controllers/Client/ProjectController.php`
```php
public function index(Request $request) {
    $user = $request->user();
    
    if ($user->isClientProject() && $user->project_id) {
        return redirect()->route('client.projects.show', $user->project_id);
    }
    // ... load project list for regular clients
}
```

### 4. Project Show View Restrictions
**File:** `app/Http/Controllers/Client/ProjectController.php`
```php
public function show(Request $request, Project $project) {
    // ...
    $isProjectSpecificUser = $request->user()->isClientProject();
    return view('client.projects.show', [
        // ...
        'isProjectSpecificUser' => $isProjectSpecificUser
    ]);
}
```

**File:** `resources/views/client/projects/show.blade.php`
```blade
@if(!$isProjectSpecificUser)
    <!-- Financials section (budget, payments, overhead) -->
@endif

@if(!empty($maintenances) && $maintenances->isNotEmpty() && !$isProjectSpecificUser)
    <!-- Maintenance pricing section -->
@endif
```

### 5. Sidebar Navigation Restrictions
**File:** `resources/views/layouts/client.blade.php`
```blade
@php
    $isProjectSpecificUser = auth()->user()->isClientProject();
@endphp

@if(!$isProjectSpecificUser)
    <!-- Hide Dashboard, Services, Domains, Licenses, Orders, Invoices, Affiliates -->
@endif

<!-- Always show Projects (but redirects to assigned project only) -->
```

### 6. Route-Level Protection
**File:** `app/Http/Middleware/BlockProjectSpecificFinancial.php`
```php
public function handle(Request $request, Closure $next): Response {
    if ($request->user() && $request->user()->isClientProject()) {
        abort(403, 'Access denied.');
    }
    return $next($request);
}
```

**File:** `bootstrap/app.php`
```php
'project.financial' => \App\Http\Middleware\BlockProjectSpecificFinancial::class,
```

**File:** `routes/web.php`
- Applied `project.financial` middleware to:
  - `/client/dashboard`
  - `/client/invoices/*` (all invoice routes)
  - `/client/services/*` (all service routes)
  - `/client/domains/*` (all domain routes)
  - `/client/licenses/*` (all license routes)
  - `/client/orders/*` (all order routes)
  - `/client/affiliates/*` (all affiliate routes)

## Security Layers

1. **Controller-Level**: Redirects prevent access at the controller
2. **View-Level**: Conditional rendering hides sensitive data
3. **Route-Level**: Middleware blocks unauthorized route access
4. **Navigation-Level**: Sidebar hides inaccessible menu items

## Testing Checklist

To verify restrictions are working:

1. ✅ Login via `/login` with project-specific credentials
2. ✅ Should redirect to `/client/projects/{project_id}`
3. ✅ Can see project details, tasks, and chat
4. ✅ Cannot see budget, payments, or maintenance pricing
5. ✅ Sidebar only shows "Projects" menu item
6. ✅ Attempting to access `/client/dashboard` redirects to project
7. ✅ Attempting to access `/client/invoices` returns 403 Forbidden
8. ✅ Attempting to access `/client/services` returns 403 Forbidden
9. ✅ Attempting to access `/client/orders` returns 403 Forbidden
10. ✅ Can edit profile at `/client/profile`
11. ✅ Can create and view tasks
12. ✅ Can use project chat and task chat
13. ✅ Can upload and view attachments

## User Experience

**Project-Specific User Login Flow:**
1. User logs in at `/login`
2. Automatically redirected to their assigned project
3. Sees only project information (no financial data)
4. Sidebar shows minimal navigation (Projects only)
5. Can work on tasks and communicate via chat
6. Cannot navigate to any financial sections
7. Direct URL attempts to financial pages result in 403 errors

## Notes

- Project-specific users share the same authentication guard as regular clients
- Access control is enforced through multiple layers for security
- The `isClientProject()` method on the User model identifies project-specific users
- The `project_id` field determines which project they can access
