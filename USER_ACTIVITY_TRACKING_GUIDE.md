# User Activity Tracking System - Implementation Guide

## Overview

A **generic, polymorphic user activity tracking system** that works across all user types (Employee, Customer, Sales Representative, and Admin/Web users) with session management, daily aggregation, and admin dashboard.

**Key Features:**
- ✅ Polymorphic session & activity tracking for multiple user types
- ✅ Automatic login/logout event handling per guard
- ✅ Throttled heartbeat middleware (60-second updates, 5-minute inactivity cutoff)
- ✅ Daily activity aggregation for efficient reporting
- ✅ Live online status indicator (green dot if active within 2 minutes)
- ✅ Admin summary dashboard with user type filtering
- ✅ Date range filtering for custom period analysis
- ✅ Automatic stale session cleanup (hourly scheduled task)
- ✅ Query-optimized, fully cached (60 seconds)
- ✅ No N+1 queries

## Architecture

### Database Schema

#### `user_sessions` Table
Tracks individual login sessions with polymorphic user references.

```sql
Columns:
- id (primary key)
- user_type (string)        // e.g., App\Models\Employee, App\Models\User, App\Models\Customer
- user_id (bigint)          // polymorphic ID
- guard (string)            // 'employee', 'web', 'client', 'rep'
- session_id (string, index)
- login_at (datetime, index)
- logout_at (nullable datetime, index)
- last_seen_at (datetime, index) // tracks last request time
- active_seconds (bigint)   // accumulated active time (< 5 min inactivity)
- ip_address (nullable)
- user_agent (nullable)
- created_at, updated_at

Indexes:
- (user_type, user_id, login_at)  // polymorphic + chronological
- (guard, login_at)               // filter by guard
- (session_id)                    // session lookup
- (last_seen_at)                  // online status detection
- (logout_at)                     // find stale sessions
```

#### `user_activity_dailies` Table
Pre-aggregated daily activity totals per user/guard combination.

```sql
Columns:
- id (primary key)
- user_type (string)
- user_id (bigint)
- guard (string)
- date (date, index)
- sessions_count (int)        // number of login sessions that day
- active_seconds (bigint)     // total active seconds that day
- first_login_at (nullable)   // first login time of the day
- last_seen_at (nullable)     // last activity time of the day
- created_at, updated_at

Unique Index:
- (user_type, user_id, guard, date)  // prevent duplicate daily records

Indexes:
- (guard, date)               // efficient daily filtering
```

### Models & Trait

**Trait: `App\Models\Concerns\HasActivityTracking`**
- `sessions()` - MorphMany relationship to UserSession
- `activityDaily()` - MorphMany relationship to UserActivityDaily
- `isOnline($minutes = 2)` - Checks if open session with recent activity

**Applied to:**
- `Employee` (guards: employee)
- `Customer` (guards: web, tracked via User model)
- `SalesRepresentative` (guards: web)
- `User` (guards: web, client, admin roles)

**Models:**
- `UserSession` - Morphable model for sessions
- `UserActivityDaily` - Morphable model for daily aggregates

### Event Listeners

**RecordUserLoginSession**
- Fires on `Illuminate\Auth\Events\Login` event
- Only tracks guards: employee, web, client, rep
- Creates UserSession record
- Creates/increments UserActivityDaily sessions_count
- Captures IP address and user agent
- Uses database transaction for atomicity

**RecordUserLogoutSession**
- Fires on `Illuminate\Auth\Events\Logout` event
- Marks session.logout_at = now()
- Detects via user_type + user_id + guard + session_id

### Middleware

**TrackAuthenticatedUserActivity** (`user.activity`)
- Runs on every request after controller action
- Checks guard authentication
- Throttled: Only updates DB once per 60 seconds (stored in session)
- Calculates activity delta since last_seen_at
- Only counts active time if delta ≤ 300 seconds (5-minute inactivity cutoff)
- Always updates last_seen_at
- Updates both UserSession and UserActivityDaily records
- Wrapped in DB::transaction for consistency

**Applied to:**
```
employee routes: middleware(['...',  'user.activity:employee'])
client routes:   middleware(['...', 'user.activity:web'])
rep routes:      middleware(['...', 'user.activity:web'])
admin routes:    middleware(['...', 'user.activity:web'])
```

### Scheduled Tasks

**CloseStaleUserSessions Command** (`user-sessions:close-stale`)
- Runs hourly via `Schedule::command()->hourly()`
- Threshold = config('session.lifetime') + 10 minutes, min 30 minutes
- Finds sessions where logout_at is null AND last_seen_at < threshold
- Sets logout_at = last_seen_at
- Chunks 100 records at a time (memory efficient)

### Admin Dashboard

**Route:** `GET /admin/users/activity-summary`
**Controller:** `UserActivitySummaryController`
**View:** `admin/users/activity-summary.blade.php`

**Features:**
- User type selector: Employees, Customers, Sales Reps, Admin/Web Users
- Optional: Specific user filter (dropdown)
- Optional: Date range filter (from/to dates)
- Displays per user:
  - Online status (green/gray dot)
  - Today metrics: sessions + duration
  - Week metrics: sessions + duration (Mon-Today)
  - Month metrics: sessions + duration (1st-Today)
  - Range metrics: (if from/to provided)
  - Last login timestamp
  - Last seen timestamp (with "ago" format)

**Performance:**
- Single efficient query with subqueries (no N+1)
- Results cached 60 seconds (invalidates when filters change)
- Cache key includes all filter parameters

## Configuration

### Inactivity Cutoff (5 minutes)
File: `app/Http/Middleware/TrackAuthenticatedUserActivity.php`
```php
private const INACTIVITY_CUTOFF_SECONDS = 300; // 5 minutes
```

### Heartbeat Throttle (60 seconds)
File: `app/Http/Middleware/TrackAuthenticatedUserActivity.php`
```php
private const HEARTBEAT_THROTTLE_SECONDS = 60;
```

### Online Window (2 minutes)
File: `app/Models/Concerns/HasActivityTracking.php`
```php
public function isOnline(int $minutes = 2): bool
```

### Stale Session Threshold (30-130 minutes)
File: `app/Console/Commands/CloseStaleUserSessions.php`
```php
$thresholdMinutes = max($sessionLifetimeMinutes + 10, 30);
```
Uses `config('session.lifetime')` from `.env` (default: 120 minutes in `config/session.php`).

## Guards & User Types Mapping

| Guard | Model | Login Route | Track | Notes |
|-------|-------|-------------|-------|-------|
| `employee` | Employee | /employee/login | ✅ | Separate Authenticatable |
| `web` | User | /login, /admin/login | ✅ | Unified for clients, admins, reps |
| `client` | User (role='client') | /login | ✅ | Tracked via web guard |
| `rep` | SalesRepresentative (via User) | /login | ✅ | Tracked via web guard |

**Note:** Only the above guards are tracked. API token auth is excluded by design.

## File Structure

```
database/
  migrations/
    2026_01_14_000001_create_user_sessions_table.php
    2026_01_14_000002_create_user_activity_daily_table.php

app/
  Models/
    UserSession.php
    UserActivityDaily.php
    Concerns/
      HasActivityTracking.php (trait)
  
  Http/
    Middleware/
      TrackAuthenticatedUserActivity.php
    Controllers/
      Admin/
        UserActivitySummaryController.php
  
  Listeners/
    RecordUserLoginSession.php
    RecordUserLogoutSession.php
  
  Providers/
    ActivityTrackingEventServiceProvider.php
  
  Console/
    Commands/
      CloseStaleUserSessions.php

resources/
  views/
    admin/
      users/
        activity-summary.blade.php

routes/
  web.php (updated with user.activity middleware)
  console.php (updated with schedule)

tests/
  Feature/
    UserActivityTrackingTest.php
```

## Usage Examples

### Check if User is Online
```php
$employee = Employee::find(1);

// Default: online if active within last 2 minutes
if ($employee->isOnline()) {
    echo "Employee is currently active";
}

// Custom window: online if active within last 5 minutes
if ($employee->isOnline(5)) {
    echo "Employee active within last 5 minutes";
}
```

### Get User Sessions
```php
$employee = Employee::find(1);

// All sessions
$sessions = $employee->sessions()->get();

// Open sessions only
$openSessions = $employee->sessions()
    ->whereNull('logout_at')
    ->get();

// Check session activity
foreach ($sessions as $session) {
    echo $session->login_at;      // Login time
    echo $session->logout_at;     // Logout time (null if still open)
    echo $session->active_seconds; // Accumulated active time
    echo $session->last_seen_at;  // Last request time
}
```

### Get Daily Activity
```php
$employee = Employee::find(1);

// Today's activity
$today = $employee->activityDaily()
    ->where('date', today())
    ->first();

echo $today->sessions_count;  // Number of logins today
echo $today->active_seconds;  // Total active seconds today

// Format duration
$hours = floor($today->active_seconds / 3600);
$minutes = floor(($today->active_seconds % 3600) / 60);
echo "{$hours}h {$minutes}m"; // e.g., "2h 30m"
```

### Query Activity Data
```php
// Total sessions this week
$weekSessions = UserActivityDaily::where('user_type', Employee::class)
    ->where('user_id', 1)
    ->whereBetween('date', [now()->startOfWeek(), now()])
    ->sum('sessions_count');

// Average session duration (all employees)
$avgDuration = UserActivityDaily::where('guard', 'employee')
    ->where('date', today())
    ->avg('active_seconds'); // in seconds

// Most active user today
$mostActive = UserActivityDaily::where('guard', 'employee')
    ->where('date', today())
    ->orderByDesc('active_seconds')
    ->first();
```

### Admin Summary Page
```
Navigate to: /admin/users/activity-summary

Features:
- Type selector: Employees, Customers, Sales Reps, Admin/Web Users
- User filter: Select specific user (optional)
- Date range: From/To dates (optional)
- Columns: Name | Today | Week | Month | [Range] | Last Seen | Last Login
- Online indicator: Green dot if active, gray if offline
```

## Testing

Run all activity tracking tests:
```bash
php artisan test tests/Feature/UserActivityTrackingTest.php
```

**Test Coverage:**
1. ✅ Employee login creates session and daily record
2. ✅ Customer login creates session and daily record
3. ✅ Sales rep login creates session and daily record
4. ✅ isOnline() detection with time windows
5. ✅ Activity summary aggregation (today/week/month)
6. ✅ Middleware activity tracking
7. ✅ User logout closes session
8. ✅ Admin summary respects user type filter

## Security Considerations

- ✅ Only authenticated users tracked (checked per guard)
- ✅ IP address and user agent captured for audit
- ✅ No sensitive data stored beyond authentication metadata
- ✅ Admin-only access to summary dashboard (via admin middleware)
- ✅ Database transactions prevent race conditions
- ✅ Polymorphic queries ensure data isolation between user types
- ✅ Event listeners silently fail without impacting user login/logout

## Performance Optimizations

- ✅ **Indexed lookups:** Composite and single indexes on session queries
- ✅ **Throttled writes:** Heartbeat updates max once per 60 seconds
- ✅ **Daily aggregation:** Pre-computed totals (no calculation on-the-fly)
- ✅ **Efficient queries:** Subqueries with selectRaw, no N+1 problems
- ✅ **Caching:** Summary results cached 60 seconds per filter combination
- ✅ **Chunked cleanup:** Stale sessions closed in batches of 100
- ✅ **Minimal middleware:** Runs after response sent (non-blocking)

## Known Limitations

1. **Session Regeneration:** Works transparently (latest session by employee_id reused)
2. **API Auth:** Token-based auth excluded by design (use web/session guards)
3. **Real-time Updates:** Heartbeat updates throttled to 60 seconds (not real-time)
4. **Timezone:** Uses app timezone from config/app.php
5. **Multiple Sessions:** Each login creates new session record (not consolidated)

## Optional Enhancements

1. **WebSocket Integration:** Real-time online status via Pusher/Laravel Echo
2. **Detailed Activity Log:** Track specific actions (page views, form submissions)
3. **Automated Reporting:** Email summaries on schedule
4. **Data Retention:** Archive old sessions/activity after 90 days
5. **Geolocation:** Map IP addresses to locations
6. **Device Tracking:** Distinguish between devices (mobile vs desktop)
7. **Bulk Export:** CSV/Excel download of activity reports
8. **Charts & Analytics:** Visual dashboards with trend analysis

## Deployment Checklist

- [ ] Run migrations: `php artisan migrate`
- [ ] Clear caches: `php artisan cache:clear && php artisan route:clear`
- [ ] Verify EventServiceProvider registered in `bootstrap/app.php`
- [ ] Verify middleware alias `user.activity` in `bootstrap/app.php`
- [ ] Ensure Laravel scheduler running: `* * * * * cd /app && php artisan schedule:run >> /dev/null 2>&1`
- [ ] Run tests: `php artisan test tests/Feature/UserActivityTrackingTest.php`
- [ ] Test admin access: Navigate to `/admin/users/activity-summary`
- [ ] Verify employee login creates session: Check database for user_sessions record
- [ ] Test activity tracking: Navigate routes and verify active_seconds incrementing

## Troubleshooting

### Sessions not being created on login
1. Check `app/Providers/ActivityTrackingEventServiceProvider.php` is registered
2. Verify Login event is firing: Add log to `RecordUserLoginSession`
3. Check database transaction errors in logs
4. Verify user's guard matches tracked guards (employee, web, client, rep)

### Activity not being tracked
1. Verify `user.activity` middleware applied to route group
2. Check session throttle time (60 seconds) hasn't been exceeded
3. Ensure `TrackAuthenticatedUserActivity` has no syntax errors
4. Check for database write errors in logs

### Online status not updating
1. Verify last_seen_at is being updated (check middleware)
2. Check isOnline() window parameter (default 2 minutes)
3. Ensure session is still open (logout_at is null)
4. Verify guard matches user's authenticated guard

### Stale sessions not closing
1. Verify scheduler is running: `php artisan schedule:list`
2. Check cron job exists: `crontab -l`
3. Run command manually: `php artisan user-sessions:close-stale`
4. Check `config('session.lifetime')` setting

## Support & Maintenance

**Key Files to Monitor:**
- `app/Http/Middleware/TrackAuthenticatedUserActivity.php` - Activity tracking logic
- `app/Listeners/RecordUserLoginSession.php` - Session creation
- `app/Console/Commands/CloseStaleUserSessions.php` - Stale cleanup
- `routes/web.php` - Middleware application
- `routes/console.php` - Scheduler configuration

**Maintenance Tasks:**
- Monthly: Review database size of user_sessions and user_activity_dailies
- Quarterly: Analyze query performance and index usage
- As needed: Adjust throttle/cutoff times based on usage patterns
- Annually: Archive old activity data (optional enhancement)

---

**Implementation Status:** ✅ Complete
**Last Updated:** January 14, 2026
**Supports:** Employee, Customer, SalesRep, Admin/Web users
**Guards:** employee, web, client, rep
