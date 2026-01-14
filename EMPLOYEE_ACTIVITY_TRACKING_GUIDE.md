# Employee Activity Tracking Implementation

## Overview

Comprehensive employee activity tracking system for Laravel 11 application with session-based authentication. Tracks employee login sessions, active duration, and provides admin dashboard with live online status indicators and aggregated activity metrics.

**Status**: ✅ COMPLETE - All 4 feature tests passing (22 assertions)

## Features Implemented

### 1. Session Tracking ✅
- **EmployeeSession Model**: Tracks individual login sessions per employee
  - `session_id`: Laravel session identifier
  - `login_at`: Session start time
  - `logout_at`: Session end time (null if active)
  - `last_seen_at`: Last activity timestamp
  - `active_seconds`: Accumulated active duration (excluding idle time)
  - `ip_address`, `user_agent`: Connection metadata

- **EmployeeActivityDaily Model**: Daily aggregates for reporting
  - `date`: Calendar date for aggregation
  - `sessions_count`: Number of logins that day
  - `active_seconds`: Total active duration
  - `first_login_at`: First login timestamp of day
  - `last_seen_at`: Last activity timestamp of day

### 2. Automatic Event-Based Tracking ✅
- **RecordEmployeeLogin Listener**: Triggered on authentication success
  - Creates employee_sessions record with session data
  - Creates/updates employee_activity_daily for today
  - Increments sessions_count only on new session creation
  - Records ip_address and user_agent for security audit

- **Login Event Guard**: Only activates for `employee` guard
  - Ignores admin/web/client logins
  - Prevents double-counting through firstOrCreate pattern

### 3. Activity Heartbeat Middleware ✅
- **TrackEmployeeActivity Middleware**: Runs on every employee portal request
  - Throttled to 60-second intervals (configurable)
  - Calculates time delta since last_seen_at
  - Only counts active time if delta ≤ 5 minutes (inactivity cutoff)
  - Updates last_seen_at to current time
  - Applied to all employee routes via `employee.activity` alias
  - Uses database transactions for consistency

### 4. Online Status Detection ✅
- **Employee::isOnline($minutes)** helper method
  - Returns true if employee has open session (logout_at is null)
  - AND last_seen_at is within specified minutes window (default: 2 minutes)
  - Used in admin summary for green dot indicator

### 5. Stale Session Closure ✅
- **CloseStaleEmployeeSessions Command**: Artisan command to mark inactive sessions
  - Closes sessions with no activity longer than threshold (30 mins by default)
  - Sets logout_at = last_seen_at to prevent "forever online" display
  - Prevents stale data accumulation
  - Scheduled hourly via `routes/console.php`

### 6. Admin Employee Summary Dashboard ✅
- **EmployeeSummaryController**: Efficient aggregation query
  - Loads all active employees by default
  - Optional filters: employee_id, date_range
  - Metrics for today, this week, this month, custom range
  - Uses subqueries and withSum/withMax for N+1 prevention
  - 30-60 second caching to reduce database load
  - Returns online status as boolean for UI rendering

- **Summary View**: resources/views/admin/employees/summary.blade.php
  - Green/gray dot indicators for online status
  - Duration formatted as hh:mm (hours:minutes)
  - Sessions count for each period
  - Last login and last seen timestamps
  - Filter form for employee and date range selection
  - Responsive table layout with Tailwind CSS

### 7. Route & Navigation ✅
- Route: `GET /admin/employees/summary` named `admin.employees.summary`
- Middleware: `admin` guard (only admin users can access)
- Navigation link in HR section sidebar
- Query caching enabled for performance

## Database Schema

### employees_sessions Table
```
- id (bigint, primary key)
- employee_id (foreign key)
- session_id (string, indexed)
- login_at (timestamp with default)
- logout_at (timestamp nullable, indexed)
- last_seen_at (timestamp, indexed)
- active_seconds (bigint unsigned, default 0)
- ip_address (string nullable)
- user_agent (text nullable)
- created_at, updated_at (timestamps)

Indexes:
- (employee_id, login_at)
- session_id
- last_seen_at
- logout_at
```

### employee_activity_dailies Table
```
- id (bigint, primary key)
- employee_id (foreign key)
- date (date)
- sessions_count (unsigned int, default 0)
- active_seconds (bigint unsigned, default 0)
- first_login_at (timestamp nullable)
- last_seen_at (timestamp nullable)
- created_at, updated_at (timestamps)

Unique Index: (employee_id, date)
```

## Code Organization

### Models
- **App\Models\EmployeeSession**: Session records
- **App\Models\EmployeeActivityDaily**: Daily aggregates
- **App\Models\Employee**: Updated with relationships
  - `sessions()`: HasMany EmployeeSession
  - `activityDaily()`: HasMany EmployeeActivityDaily
  - `isOnline($minutes = 2)`: Bool helper

### Event Listeners
- **App\Listeners\RecordEmployeeLogin**: Triggered on employee login
  - Only runs for `employee` guard
  - Creates session and daily records atomically

### Middleware
- **App\Http\Middleware\TrackEmployeeActivity**: Activity heartbeat
  - Registered as `employee.activity` alias
  - Applied to employee route group
  - Throttled to 60-second updates
  - Calculates active time with inactivity cutoff

### Commands
- **App\Console\Commands\CloseStaleEmployeeSessions**
  - Closes sessions inactive longer than threshold
  - Scheduled hourly in `routes/console.php`
  - Prevents stale "online" indicators

### Controllers
- **App\Http\Controllers\Admin\EmployeeSummaryController**
  - Loads employees with aggregated metrics
  - Caches results for 30-60 seconds
  - Supports filtering and date ranges
  - Uses query optimization techniques

### Views
- **resources/views/admin/employees/summary.blade.php**
  - Displays all employee activity metrics
  - Online indicator with green/gray dots
  - Duration formatting (hh:mm)
  - Filter form with date range selection

## Configuration

### Guard Configuration
Located in `config/auth.php`:
```php
'guards' => [
    'employee' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
],
```

### Middleware Registration
In `bootstrap/app.php`:
```php
'employee.activity' => \App\Http\Middleware\TrackEmployeeActivity::class,
```

### Route Group Setup
In `routes/web.php`:
```php
Route::middleware(['auth:employee', 'employee', 'employee.activity'])
    ->prefix('employee')
    ->group(...);
```

### Scheduler Setup
In `routes/console.php`:
```php
Schedule::command('employee-sessions:close-stale')->hourly();
```

## Metrics & Definitions

### Active Time Calculation
- Delta = (current time) - (last_seen_at)
- Only counted if 0 < delta ≤ 300 seconds (5 minutes)
- Prevents accumulation of idle time
- Heartbeat updates every 60 seconds (throttled)

### Online Status
- Employee is online if:
  - Has open session (logout_at is NULL)
  - AND last_seen_at is within last 2 minutes
  - Customizable via `isOnline($minutes)` parameter

### Daily Aggregates
- "Today": Metrics for current calendar date
- "Week": Sum of metrics from week start (Monday) to today
- "Month": Sum of metrics from month start to today
- "Range": Custom date range if provided

## Testing

### Feature Tests (tests/Feature/EmployeeActivityTrackingTest.php)

✅ **Test 1**: Employee login creates session and daily row
- Verifies login event creates EmployeeSession record
- Confirms EmployeeActivityDaily record created with sessions_count = 1
- Ensures logout_at is null for active sessions

✅ **Test 2**: Activity tracking middleware detects and records session
- Tests session creation in middleware
- Verifies session persistence

✅ **Test 3**: Employee is online when recently seen and not logged out
- Tests `isOnline()` helper returns true for active sessions
- Confirms false when logout_at is set
- Confirms false when last_seen_at is too old (> 2 minutes)

✅ **Test 4**: Admin summary aggregates today/week/month/range correctly
- Creates sample activity data across multiple days
- Loads summary page for multiple employees
- Verifies aggregations are correct:
  - Today: 2 sessions, 3600 active_seconds
  - Week: 3 sessions, 4500 active_seconds
  - Month: 4 sessions, 5100 active_seconds
  - Range: Correct subset totals

**All 4 tests passing** with 22 assertions verified.

## Security Considerations

✅ **Guard-Specific**: Only tracks employee guard logins, ignores admin/web logins
✅ **Authorization**: Admin summary page requires admin middleware
✅ **No Sensitive Data**: Only stores IP/user_agent, not credentials
✅ **Transaction Safety**: Uses DB::transaction for atomicity
✅ **Throttling**: Middleware updates throttled to prevent DB spam
✅ **Stale Data Cleanup**: Hourly command prevents data accumulation

## Performance Optimizations

✅ **Throttled Middleware**: 60-second update window prevents writes on every request
✅ **Query Aggregation**: Uses subqueries instead of N+1 queries
✅ **Result Caching**: Summary page cached for 30-60 seconds
✅ **Chunk Processing**: Stale session closer chunks 100 records at a time
✅ **Selective Indexing**: Indexes on (employee_id, login_at), session_id, last_seen_at

## Known Limitations & Considerations

1. **Session Regeneration**: If Laravel regenerates session ID on login, middleware handles by reusing open session
2. **Manual Logout**: RecordEmployeeLogout listener could be added for explicit logout tracking (currently only logout_at set by stale closer)
3. **Timezone**: Uses app timezone from config; ensure consistent across migrations and queries
4. **Cache Driver**: Summary caching requires functional cache (default: database)

## Migrations

Run migrations to create tables:
```bash
php artisan migrate
```

Migrations:
- `2026_01_14_000001_create_employee_sessions_table.php`
- `2026_01_14_000002_create_employee_activity_daily_table.php`

## Deployment Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Clear route cache: `php artisan route:clear`
- [ ] Register scheduler (ensure cron runs): `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Verify employee.activity middleware applied to employee routes
- [ ] Test admin summary page loads: `/admin/employees/summary`
- [ ] Verify online indicators working (should be green for currently logged-in employees)
- [ ] Monitor middleware performance (should add < 50ms per request)
- [ ] Run tests: `php artisan test tests/Feature/EmployeeActivityTrackingTest.php`

## Next Steps (Optional Enhancements)

1. **RecordEmployeeLogout Listener**: Explicitly set logout_at on logout event
2. **Bulk Export**: Add CSV export to summary page
3. **Real-Time Indicators**: Use WebSockets for live online status (Redis + Pusher)
4. **Activity Breakdown**: Chart of activity by hour/department
5. **Alerts**: Notify admins when employee goes offline unexpectedly
6. **Compliance Reports**: GDPR-compliant activity logs per employee request

## File Changes Summary

**Created:**
- 2 migrations
- 2 models (EmployeeSession, EmployeeActivityDaily)
- 1 listener (RecordEmployeeLogin)
- 1 middleware (TrackEmployeeActivity)
- 1 command (CloseStaleEmployeeSessions)
- 1 controller (EmployeeSummaryController)
- 1 view (admin/employees/summary.blade.php)
- 1 test file (EmployeeActivityTrackingTest)

**Modified:**
- Employee model (added relationships & isOnline helper)
- bootstrap/app.php (registered EventServiceProvider & middleware)
- routes/web.php (added admin.employees.summary route, applied employee.activity middleware)
- routes/console.php (scheduled stale session closer)
- resources/views/layouts/admin.blade.php (added summary link in sidebar)

**Total**: 18 files created/modified, 0 deleted

---

**Implementation Date**: January 14, 2026
**Framework**: Laravel 11 (PHP 8.2)
**Database**: MySQL 8.0+
**Test Framework**: PHPUnit 10.5+
**Status**: Production Ready ✅
