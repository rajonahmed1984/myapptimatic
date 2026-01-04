# Cron Job Setup Guide

## Overview
Your application now uses **two cron jobs** similar to WHMCS:

1. **Frequent Cron** - Every 5 minutes (time-sensitive tasks)
2. **Daily Billing Cron** - Once daily at 12:00 AM (batch operations)

---

## 1. Frequent Cron (Every 5 Minutes)

### Purpose
Handles time-sensitive operations:
- Payment processing
- License verification
- Email queue processing
- System health monitoring

### cPanel Setup

**Schedule:** Custom (*/5 * * * *)

**Command:**
```bash
/usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron-frequent.php
```

**Cron Expression:** `*/5 * * * *`

---

## 2. Daily Billing Cron (12:00 AM)

### Purpose
Handles daily batch operations:
- Invoice generation
- Overdue processing
- Late fee application
- Suspensions/Terminations
- Automated reminders
- Ticket auto-close

### cPanel Setup

**Schedule:** Once Per Day (12:00am)

**Command:**
```bash
/usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron.php
```

**Cron Expression:** `0 0 * * *`

---

## Setup Instructions

### Step 1: Log into cPanel
1. Navigate to your cPanel dashboard
2. Find "Cron Jobs" under Advanced section

### Step 2: Add Frequent Cron
1. Click "Add New Cron Job"
2. Common Settings: **Custom**
3. Minute: `*/5`
4. Hour: `*`
5. Day: `*`
6. Month: `*`
7. Weekday: `*`
8. Command:
   ```
   /usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron-frequent.php
   ```
9. Click "Add New Cron Job"

### Step 3: Add Daily Billing Cron
1. Click "Add New Cron Job"
2. Common Settings: **Once Per Day (0 0 * * *)**
3. Command:
   ```
   /usr/local/bin/ea-php82 /home/apptimatic/my.apptimatic.com/crons/cron.php
   ```
4. Click "Add New Cron Job"

### Step 4: Verify PHP Version
Replace `ea-php82` with your actual PHP version:
- `ea-php81` for PHP 8.1
- `ea-php82` for PHP 8.2
- `ea-php83` for PHP 8.3

To find your PHP version, run in terminal:
```bash
php -v
```

---

## Monitoring

### Check Last Run Times
In your admin panel, check Settings table:
- `frequent_cron_last_run` - Last frequent cron execution
- `billing_last_run_at` - Last billing cron execution
- `billing_last_status` - Status of last billing run

### View Cron Logs
Check your application logs:
```bash
tail -f storage/logs/laravel.log
```

### System Logs
Your crons log to `system_logs` table with module type 'module'

---

## Features

### ✅ Built-in Protection
- **Frequent Cron:** Lock file prevents concurrent execution
- **Daily Billing Cron:** Duplicate prevention for same-day runs
- Both track execution times

### ✅ WHMCS-Style Architecture
- Separate frequent and daily operations
- Batch processing for efficiency
- Comprehensive logging
- Email notifications

### ✅ Time-Sensitive Tasks (5-minute cron)
- Queue processing
- Payment verification
- License checks
- System monitoring

### ✅ Batch Operations (daily cron)
- Invoice generation
- Status updates
- Automated actions
- Customer notifications

---

## Troubleshooting

### Cron Not Running?
1. Check PHP path: `/usr/local/bin/ea-php82 -v`
2. Verify file paths are absolute
3. Check file permissions: `chmod +x crons/*.php`
4. Review cPanel cron log emails

### Previous Cron Still Running?
The frequent cron uses lock files to prevent overlapping. If stuck:
```bash
rm storage/framework/schedule-frequent.lock
```

### Need to Test Manually?
```bash
php crons/cron-frequent.php
php crons/cron.php
```

---

## Email Notifications

Both crons can email you on completion/errors. Configure in:
- Admin Settings > Email Templates
- Check "Enable Cron Notifications"

---

## Support

For issues or questions:
- Check `storage/logs/laravel.log`
- Review System Logs in admin panel
- Contact support with log details
