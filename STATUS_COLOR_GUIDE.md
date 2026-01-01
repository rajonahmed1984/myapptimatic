# Status Color Standards

This document defines the standardized color scheme for all status indicators in MyApptimatic.

## Color Philosophy

Colors are assigned based on **activity level and urgency**:

- **Green (Emerald)**: Active, healthy, paid, synced, success
- **Blue**: In-progress, running, neutral activity  
- **Amber/Yellow**: Warning, pending, outdated, requires attention
- **Rose/Red**: Critical, blocked, suspended, failed, error
- **Slate/Gray**: Inactive, archived, neutral, unsynced

## Status Categories

### Invoice Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `paid` | Emerald | `bg-emerald-100 text-emerald-700` | Payment received, active |
| `unpaid` | Amber | `bg-amber-100 text-amber-700` | Awaiting payment, requires action |
| `overdue` | Rose | `bg-rose-100 text-rose-700` | Payment late, access may be blocked |
| `cancelled` | Slate | `bg-slate-100 text-slate-700` | Voided, archived |

### Subscription Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `active` | Emerald | `bg-emerald-100 text-emerald-700` | Active, customer has access |
| `suspended` | Rose | `bg-rose-100 text-rose-700` | Paused due to overdue billing |
| `terminated` | Slate | `bg-slate-100 text-slate-700` | Cancelled, archived |

### License Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `active` | Emerald | `bg-emerald-100 text-emerald-700` | Valid, in use |
| `suspended` | Rose | `bg-rose-100 text-rose-700` | Blocked due to overdue billing |
| `revoked` | Slate | `bg-slate-100 text-slate-700` | Expired or terminated |

### Sync Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `synced` | Emerald | `bg-emerald-100 text-emerald-700` | Updated within 24 hours |
| `stale` | Amber | `bg-amber-100 text-amber-700` | Hasn't synced in >24 hours |
| `never` | Slate | `bg-slate-100 text-slate-600` | No sync data yet |

### Access Blocking
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `blocked` / `access_blocked` | Rose | `bg-rose-100 text-rose-700` | Access denied due to overdue billing |
| `unblocked` | Emerald | `bg-emerald-100 text-emerald-700` | Access restored after payment |

### Automation Run Status
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `success` | Emerald | `bg-emerald-100 text-emerald-700` | Completed successfully |
| `running` | Blue | `bg-blue-100 text-blue-700` | Currently executing |
| `failed` | Rose | `bg-rose-100 text-rose-700` | Error during execution |
| `pending` | Amber | `bg-amber-100 text-amber-700` | Waiting to run |

### Support Ticket Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `open` | Blue | `bg-blue-100 text-blue-700` | Awaiting response/resolution |
| `closed` | Slate | `bg-slate-100 text-slate-700` | Resolved, archived |

### Generic Statuses
| Status | Color | CSS Classes | Meaning |
|--------|-------|-------------|---------|
| `inactive` | Slate | `bg-slate-100 text-slate-600` | Not currently active |

## Implementation

### Using the Helper Class

```php
use App\Support\StatusColorHelper;

// Get full color array
$colors = StatusColorHelper::getStatusColors('paid');
// ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'color' => 'emerald', 'dot' => 'bg-emerald-500', 'icon' => '✓']

// Get specific parts
$bgClass = StatusColorHelper::getBgClass('overdue');           // 'bg-rose-100'
$textClass = StatusColorHelper::getTextClass('active');        // 'text-emerald-700'
$badge = StatusColorHelper::getBadgeClasses('synced');         // 'bg-emerald-100 text-emerald-700'

// Get color map for a category
$invoiceColors = StatusColorHelper::getColorMap('invoice');
```

### Using Blade Component

In any Blade template:

```blade
<!-- Basic usage -->
<x-status-badge :status="$invoice->status" />

<!-- With custom label -->
<x-status-badge :status="$license->status" label="License Active" />

<!-- In conditions -->
@if($invoice->status === 'overdue')
    <x-status-badge status="overdue" />
@endif
```

### Manual Implementation

If not using components:

```blade
@php
    use App\Support\StatusColorHelper;
    $colors = StatusColorHelper::getStatusColors($invoice->status);
@endphp

<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $colors['bg'] }} {{ $colors['text'] }}">
    {{ ucfirst($invoice->status) }}
</div>
```

## Migration Guide

When updating views to use standardized colors:

### Before
```blade
@php($syncClass = $hours <= 24 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700')
<div class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $syncClass }}">
    {{ $hours <= 24 ? 'Synced' : 'Stale' }}
</div>
```

### After
```blade
<x-status-badge :status="$hours <= 24 ? 'synced' : 'stale'" />
```

## Color Psychology in Context

- **Green (Emerald)**: Everything is working, customer is active/paid → Peace of mind
- **Blue**: Activity is ongoing, no issues → Neutral/informational
- **Amber**: Attention needed, action recommended → Caution/warning
- **Rose (Red)**: Problem/critical → Urgent action needed
- **Slate/Gray**: Inactive or historical → Not relevant now

## Tailwind Classes Used

All colors use Tailwind's standard palette:
- `emerald-100` (background), `emerald-700` (text), `emerald-500` (dot)
- `blue-100`, `blue-700`, `blue-500`
- `amber-100`, `amber-700`, `amber-500`
- `rose-100`, `rose-700`, `rose-500`
- `slate-100`, `slate-600`/`slate-700`, `slate-400`/`slate-500`

## Consistency Rules

1. **Same status = Same color** across all pages
2. **Color meaning is universal** (red always means problem)
3. **Use the helper** instead of hardcoding classes
4. **Component over manual** (use `<x-status-badge>` when possible)
5. **Test all status states** when adding new features
