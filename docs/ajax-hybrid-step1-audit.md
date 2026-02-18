# Safe Hybrid AJAX Audit - STEP 1

Generated: 2026-02-18
Scope: full project scan (views, JS navigation engine, partial-response middleware, controllers)

## 1) Link Inventory

- Total anchor tags with `href`: **506**
- Module distribution:
  - admin: 311
  - client: 62
  - layouts: 39
  - projects: 19
  - employee: 14
  - auth: 13
  - tasks: 11
  - rep: 10
  - support: 8
  - errors: 6
  - emails: 5
  - public: 4
  - sales: 2
  - partials: 1
  - welcome.blade.php: 1

Link behavior risk markers (literal scan):
- `target="_blank"`: **21**
- `href="http` (absolute external-like): **13**
- `href="#` (hash links): **2**
- `href="mailto:`: **1**
- `href="tel:`: **0**
- `href="javascript:`: **1**
- `data-native="true"`: **0**

## 2) Form Inventory

- Total `<form>` tags: **211**
- Module distribution:
  - admin: 136
  - client: 14
  - layouts: 12
  - projects: 11
  - auth: 11
  - employee: 10
  - tasks: 6
  - support: 5
  - rep: 3
  - sales: 1
  - project-client: 1
  - public: 1

Form behavior markers:
- `data-ajax-form="true"`: **43**
- `data-ajax-modal="true"` (trigger usage in views): **21**
- `enctype="multipart/form-data"`: **36**
- critical-like keyword match in form tags (`login/logout/password/2fa/payment/checkout/export/download`): **26**
- `data-native="true"`: **0**

## 3) Controller Partial Handling List

### Legacy HTMX-style handling (`HX-Request`) found in controllers (12)
- `app/Http/Controllers/Admin/CustomerController.php:66`
- `app/Http/Controllers/Admin/ExpenseController.php:134`
- `app/Http/Controllers/Admin/IncomeController.php:217`
- `app/Http/Controllers/Admin/LicenseController.php:86`
- `app/Http/Controllers/Admin/PaymentProofController.php:59`
- `app/Http/Controllers/Admin/ProjectMaintenanceController.php:55`
- `app/Http/Controllers/Admin/SalesRepresentativeController.php:65`
- `app/Http/Controllers/Admin/SubscriptionController.php:26`
- `app/Http/Controllers/Admin/TasksController.php:51`
- `app/Http/Controllers/Client/TasksController.php:51`
- `app/Http/Controllers/Employee/TasksController.php:51`
- `app/Http/Controllers/SalesRep/TasksController.php:51`

### Partial middleware engine
- `app/Http/Middleware/HandlePartialResponse.php`
  - uses `X-Partial`
  - emits `X-Partial-Response: true`
  - currently accepts `$request->ajax()` fallback too

### JSON-first handling also present
- several controllers already use `expectsJson()` for validation/action responses.

## 4) `hx-boost` Usage Map

- Total `hx-boost=` occurrences: **80**
- Module distribution:
  - admin: 59
  - client: 11
  - projects: 5
  - tasks: 2
  - layouts: 1
  - rep: 1
  - employee: 1

Top hotspot files:
- `resources/views/admin/invoices/partials/show-main-content.blade.php` (3)
- `resources/views/admin/customers/show.blade.php` (2)
- `resources/views/admin/customers/create.blade.php` (2)
- `resources/views/tasks/partials/index.blade.php` (2)
- `resources/views/admin/projects/create.blade.php` (2)

## 5) Broken Blade Syntax List

### Confirmed malformed template expression
- `resources/views/admin/users/edit.blade.php:13`
- Current line contains broken Blade/PHP tokenization:
  - `route('admin.users.index', $user- hx-boost="false">role)`

This is unsafe and must be fixed before broad AJAX migration.

## 6) Missing Wrapper List (`#appContent`)

### Layouts with `#appContent`
- `resources/views/layouts/admin.blade.php`
- `resources/views/layouts/client.blade.php`
- `resources/views/layouts/rep.blade.php`
- `resources/views/layouts/support.blade.php`

### Layouts missing `#appContent`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/layouts/public.blade.php`

Note: for hybrid admin/client/rep/support browsing, wrappers are present. Guest/public/app remain native-first (which is acceptable for auth/public flows).

## 7) JS Navigation Stack Status

### Current behavior split
- `resources/js/ajax-nav.js`
  - intercepts **sidebar links only**
  - manages `pushState/popstate`
  - fetches partial via `X-Partial`
- `resources/js/ajax-engine.js`
  - intercepts forms/actions
  - intercepts only some content links (`data-ajax-nav="true"` or pagination)

### Gap
- content-area standard links are **not globally intercepted** today.
- This is main reason many pages still full reload.

## 8) Safety Readiness Summary

Current architecture is **partial hybrid**, not universal hybrid:
- Good foundations: middleware partial engine, history API, delegated form engine, modal engine.
- Blocking issues before default-AJAX rollout:
  1. malformed Blade expression (users/edit)
  2. dual navigation logic split (sidebar-focused vs content-focused)
  3. no `data-native` convention yet
  4. heavy `hx-boost` legacy footprint (80)

## 9) STEP 1 Outcome

STEP 1 scan completed with required inventories:
- Link inventory: done
- Form inventory: done
- Controller partial handling list: done
- `hx-boost` usage map: done
- Broken Blade syntax list: done
- Missing wrapper list: done

Ready for STEP 2: unified safe `ajax-engine.js` (single navigation authority) with progressive enhancement + native opt-out.
