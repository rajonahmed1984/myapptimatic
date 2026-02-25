# Date/Time Standard

## Standard
- Date: `DD-MM-YYYY` (PHP: `d-m-Y`)
- Time: `hh:mm A` (12-hour with AM/PM, PHP: `h:i A`)
- DateTime: `DD-MM-YYYY hh:mm A` (PHP: `d-m-Y h:i A`)

This standard is for user-facing presentation and user-entered values only.

## Non-negotiable constraints
- Database storage format is unchanged.
- External/API integration timestamps (ISO-8601, machine payloads) remain unchanged.
- Timezone behavior uses app timezone and existing settings (`Setting::time_zone`) as already wired in `AppServiceProvider`.

## Single Source of Truth

### PHP
- Helper: `App\Support\DateTimeFormat`
- Methods:
  - `formatDate($value, $fallback = '-')`
  - `formatDateTime($value, $fallback = '-')`
  - `formatTime($value, $fallback = '-')`
  - `parseDate(?string $value)`
  - `parseDateTime(?string $value)`
  - `parseTime(?string $value)`

### React
- Helper: `resources/js/react/utils/datetime.js`
- Methods:
  - `formatDate(value, fallback = '-')`
  - `formatDateTime(value, fallback = '-')`
  - `formatTime(value, fallback = '-')`
  - `parseDate(value)`
  - `formatDateInTimeZone(value, timeZone, fallback = '-')`
  - `formatTimeInTimeZone(value, timeZone, fallback = '-')`
  - `formatDateTimeInTimeZone(value, timeZone, fallback = '-')`

## Input Parsing
- Middleware: `App\Http\Middleware\NormalizeDisplayDateInput`
- Accepts:
  - `DD-MM-YYYY`
  - `DD-MM-YYYY hh:mm AM/PM`
  - `hh:mm AM/PM`
- Normalizes to storage-friendly values before validation/persistence:
  - Date -> `Y-m-d`
  - DateTime -> `Y-m-d H:i`
  - Time -> `H:i`

## Inventory (Step 0 output)
Generated artifacts:
- `storage/app/datetime-inventory/php-format-files.txt`
- `storage/app/datetime-inventory/php-parse-files.txt`
- `storage/app/datetime-inventory/react-datetime-files.txt`
- `storage/app/datetime-inventory/date-input-files.txt`
- `storage/app/datetime-inventory/timezone-scan.txt`

Snapshot counts from this run:
- `php-format-files`: 101
- `php-parse-files`: 40
- `react-datetime-files`: 7
- `date-input-files`: 37
- `timezone-scan`: 29

## Usage Notes
- Prefer `DateTimeFormat::*` in PHP for all UI-bound display values.
- Prefer `datetime.js` helper functions in React for all client formatting.
- Do not add inline hardcoded formats like `'Y-m-d'`, `'H:i'`, `'M d, Y'` for UI output.
