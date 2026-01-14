# Sidebar Active State Implementation

## Overview

This document describes the implementation of **persistent sidebar active state highlighting** across all user roles (admin, employee, client, sales rep) with optional HTMX enhancement for smooth navigation.

## Architecture

### Design Philosophy

**Server-Side Active Detection** (Recommended)
- Active state determined by `request()->routeIs()` on the backend
- Persists across page reloads without any JavaScript
- Works without cookies or local storage
- SEO-friendly and accessible

**Optional HTMX Enhancement**
- Progressive enhancement layer for smoother UX
- Prevents full page reload during navigation
- Maintains URL in address bar with `hx-push-url="true"`
- Graceful fallback to normal navigation if HTMX is disabled

## Components & Helpers

### 1. Route Helper Functions (`app/Helpers/RouteHelper.php`)

Four exported functions automatically available globally (registered in `composer.json` autoload):

#### `isActive($routes): bool`
Checks if current route matches pattern(s).

```php
isActive('admin.dashboard')              // Single route
isActive(['admin.customers.*', 'admin.orders.*'])  // Multiple patterns
isActive('admin.projects.*')             // Wildcard patterns
```

#### `isActiveClass($routes, $activeClass, $inactiveClass): string`
Returns appropriate CSS classes based on active state.

```php
isActiveClass('admin.dashboard', 'nav-link nav-link-active', 'nav-link')
// Returns: 'nav-link nav-link-active' if on admin.dashboard
// Returns: 'nav-link' otherwise
```

#### `activeIf($condition, $activeClass, $inactiveClass): string`
Simple conditional class helper for nested menu items.

```php
activeIf(request()->routeIs('admin.projects.create'), 'active', '')
// Returns active class if condition is true
```

#### `isChildActive($parentRoutes): bool`
Determines if any child route of parent is active (for menu expansion).

```php
isChildActive(['admin.projects.*', 'admin.projects.create'])
// Returns true if current route matches any pattern
```

### 2. Blade Components

#### `x-nav-link` Component
Single-level navigation link with automatic active detection.

**Props:**
- `href` (required): Route URL
- `routes` (required): Single route pattern or array of patterns to match
- `activeClass`: CSS classes when active (default: 'nav-link nav-link-active')
- `inactiveClass`: CSS classes when inactive (default: 'nav-link')
- `badge`: Optional badge count/text to display
- `badgeClass`: CSS classes for badge (default: 'ml-auto rounded-full bg-teal-100 px-2 py-0.5 text-xs font-semibold text-teal-700')
- `useHtmx`: Enable HTMX for AJAX navigation (default: false)

**Example:**
```blade
<x-nav-link 
    :href="route('admin.dashboard')" 
    routes="admin.dashboard"
>
    <span class="h-2 w-2 rounded-full bg-current"></span>
    Dashboard
</x-nav-link>
```

**With Badge:**
```blade
<x-nav-link 
    :href="route('admin.orders.index')" 
    routes="admin.orders.*"
    :badge="$pendingOrdersCount"
>
    Orders
</x-nav-link>
```

#### `x-nav-menu` Component
Multi-level menu with automatic expansion when child is active.

**Props:**
- `href` (required): Parent menu URL
- `routes` (required): Array of route patterns to detect active state
- `label` (required): Menu label text
- `icon` (bool): Whether to show icon bullet (default: true)
- `activeClass`: CSS classes when active (default: 'nav-link nav-link-active')
- `inactiveClass`: CSS classes when inactive (default: 'nav-link')
- `badge`: Optional badge count/text

**Usage:**
```blade
<x-nav-menu 
    :href="route('admin.projects.index')" 
    :routes="['admin.projects.*']"
    label="Projects"
>
    <x-nav-link 
        :href="route('admin.projects.index')" 
        routes="admin.projects.index"
    >
        All Projects
    </x-nav-link>
    <x-nav-link 
        :href="route('admin.projects.create')" 
        routes="admin.projects.create"
    >
        Create Project
    </x-nav-link>
</x-nav-menu>
```

The component automatically:
- Shows the menu as active when any child route is active
- Displays nested items when parent is active
- Applies active styling to the currently active child

## Layout Changes

### admin.blade.php
- **Lines 30-380**: Navigation completely refactored to use components
- **Lines 440-465**: Main content wrapped with `id="main-content"` and `hx-boost="true"`
- **Lines 535-549**: HTMX event handler for post-swap updates
- **Employee & Sales Rep Sections**: Role-specific navigation using component system

### client.blade.php
- **Navigation section**: Refactored to use `x-nav-link` components
- Sections: Overview, Projects & Services, Orders & Requests, Billing & Payments, Support & Growth, Account

### rep.blade.php
- **Navigation section**: Refactored to use `x-nav-link` components
- Sections: Dashboard, Earnings (Commissions/Payouts)

## CSS Styling

Active state styling is defined in `public/css/custom.css`:

```css
.nav-link-active {
    background: rgba(20, 184, 166, 0.16);
    color: #5eead4;
}
```

The `nav-link` class provides default styling, and `nav-link-active` overrides it with teal highlight.

## HTMX Integration (Optional)

### How It Works

1. **HTMX CDN**: `unpkg.com/htmx.org@1.9.11` loaded in `head.blade.php`
2. **Boost Mode**: `hx-boost="true"` on main content area converts all link clicks to AJAX
3. **Partial Loading**: Only `#main-content` element is swapped
4. **History Management**: `hx-push-url="true"` maintains URL in address bar
5. **Server-Side Routing**: Current route still matches, so active detection works automatically

### Enabling for Specific Links

Add `use-htmx="true"` prop to `x-nav-link` component:

```blade
<x-nav-link 
    :href="route('admin.dashboard')" 
    routes="admin.dashboard"
    use-htmx="true"
>
    Dashboard
</x-nav-link>
```

### Disabling HTMX for Specific Links

Add `hx-disable` attribute to prevent AJAX navigation:

```blade
<a href="{{ route('logout') }}" hx-disable>
    Logout
</a>
```

### Server-Side Partial Support (Optional)

To optimize HTMX requests and only return content (not layout), controllers can check:

```php
// In your controller
if (request()->header('HX-Request')) {
    // Return just the content without layout
    return view('admin.dashboard', $data);
}

// Normal full-page response
return view('layouts.admin', ['content' => view('admin.dashboard', $data)]);
```

Or use the `?partial=1` query parameter:

```php
if (request()->get('partial')) {
    return view('admin.dashboard', $data);
}
```

## Testing the Implementation

### Manual Testing Checklist

#### Admin Routes
- [ ] Navigate to `/admin/dashboard` → "Dashboard" highlights
- [ ] Navigate to `/admin/customers` → "Customers" highlights, section visible
- [ ] Navigate to `/admin/projects` → "Projects" highlights, nested menu expands
- [ ] Navigate to `/admin/projects/create` → "Projects" highlights, "Create Project" child highlighted
- [ ] Navigate to `/admin/invoices` → "Invoices" highlights, nested menu shows invoice filters
- [ ] **Reload page** on `/admin/invoices` → Active states persist (KEY FEATURE)
- [ ] Navigate to `/admin/logs/activity` → "Logs" highlights, nested menu shows log types
- [ ] Test on mobile: sidebar toggle works, sidebar closes on navigation

#### Client Routes
- [ ] `/client/dashboard` → "Overview" highlights
- [ ] `/client/projects` → "Projects" highlights
- [ ] `/client/invoices` → "Invoices" highlights
- [ ] Reload page and verify active states persist

#### Sales Rep Routes
- [ ] `/rep/dashboard` → "Sales Dashboard" highlights
- [ ] `/rep/earnings` → "Commissions" highlights
- [ ] Reload and verify persistence

#### Employee Routes
- [ ] `/employee/dashboard` → "Dashboard" highlights
- [ ] `/employee/projects` → "Projects" highlights
- [ ] `/employee/timesheets` → "Timesheets" highlights

### Testing HTMX (Optional)

1. Enable HTMX on a navigation link: `use-htmx="true"`
2. Click the link and observe:
   - Page doesn't fully reload (smoother UX)
   - URL updates in address bar
   - Active state updates automatically
   - Browser back/forward buttons work correctly
3. Verify sidebar remains visible during navigation
4. Test with network throttling in DevTools to confirm partial load

## Troubleshooting

### Active State Not Showing

**Issue**: Link doesn't highlight when on that route.

**Solution**:
1. Verify route name matches: Use `request()->route()->getName()` in a debug view
2. Check route definition in `routes/web.php` - ensure route has a `.name()` or name parameter
3. Verify component is using correct route pattern: `admin.users.*` vs `admin.user.*`

### Helper Functions Not Available

**Issue**: "Undefined function isActive()" error.

**Solution**:
```bash
composer dump-autoload
php artisan cache:clear
```

### Nested Menu Not Expanding

**Issue**: Child items visible but parent menu not expanding when child is active.

**Solution**:
1. Verify `x-nav-menu` routes array includes parent pattern
2. Check `isChildActive()` helper is working: `dd(isChildActive(['admin.projects.*']))`
3. Ensure child `x-nav-link` routes match parent patterns

### HTMX Navigation Broken

**Issue**: Links don't work with HTMX enabled.

**Solution**:
1. Verify HTMX CDN is loaded: Check browser console for 404 on htmx.org
2. Check main element has `id="main-content"` and `hx-boost="true"`
3. Add `hx-disable` to links that shouldn't use AJAX (logout, file download, etc.)
4. Verify controller returns correct content type (HTML for HTMX)

## File Structure

```
app/
  Helpers/
    RouteHelper.php          # 4 exported helper functions

resources/
  views/
    components/
      nav-link.blade.php     # Single-level nav component
      nav-menu.blade.php     # Multi-level nav component
    layouts/
      admin.blade.php        # Refactored admin layout with components
      client.blade.php       # Refactored client layout with components
      rep.blade.php          # Refactored rep layout with components
      partials/
        head.blade.php       # Added HTMX CDN script
```

## Performance Notes

- **No Database Queries**: Active detection uses route matching only
- **No API Calls**: Server-side determination, no extra requests
- **No JavaScript for Baseline**: Works without any JS (fallback mode)
- **Minimal HTMX Overhead**: Optional feature, ~14KB gzipped
- **CSS**: Single teal highlight color (no extra stylesheets needed)

## Maintenance & Extending

### Adding New Navigation Items

1. Get the route name: `route('admin.new-feature.index')`
2. Add to component:
   ```blade
   <x-nav-link 
       :href="route('admin.new-feature.index')" 
       routes="admin.new-feature.*"
   >
       <span class="h-2 w-2 rounded-full bg-current"></span>
       New Feature
   </x-nav-link>
   ```

### Adding Nested Menu

Use `x-nav-menu` instead:
```blade
<x-nav-menu 
    :href="route('admin.new-feature.index')" 
    :routes="['admin.new-feature.*']"
    label="New Feature"
>
    {{-- Child items --}}
</x-nav-menu>
```

### Changing Active Highlight Color

Update `.nav-link-active` in `public/css/custom.css`:
```css
.nav-link-active {
    background: rgba(59, 130, 246, 0.16);  /* blue-500 */
    color: #3b82f6;
}
```

### Adding Badge to Navigation

Use the `badge` prop:
```blade
<x-nav-link 
    :href="route('admin.support-tickets.index')" 
    routes="admin.support-tickets.*"
    :badge="$ticketCount"
>
    Support Tickets
</x-nav-link>
```

## Browser Support

- Modern browsers: Chrome, Firefox, Safari, Edge (all recent versions)
- IE11: No HTMX support, falls back to normal navigation (active states still work)
- Mobile: Full support including touch interactions

## Summary

The sidebar active state implementation provides:

✅ **Persistent active highlighting** - Survives page reloads
✅ **Server-side detection** - No dependency on JavaScript
✅ **All roles supported** - Admin, employee, client, sales rep
✅ **Nested menu support** - Auto-expansion when child active
✅ **Clean components** - Reusable, maintainable code
✅ **Optional HTMX** - Smooth navigation without full reload
✅ **Accessible** - Semantic HTML, proper ARIA attributes
✅ **No performance impact** - Pure server-side logic

---

**Implementation Date**: February 2025
**Framework**: Laravel 11 with Blade & Tailwind CSS
**Related Files**: See File Structure section above
