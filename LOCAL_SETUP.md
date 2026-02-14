# Local Setup

## Database prerequisites

1. Start MySQL in XAMPP (or your local MySQL service manager).
2. Verify `.env` values:
   - `DB_CONNECTION=mysql`
   - `DB_HOST=127.0.0.1`
   - `DB_PORT=3306`
   - `DB_DATABASE=<your_local_db>`
   - `DB_USERNAME=<your_local_user>`
3. Clear cached config after `.env` edits:

```bash
php artisan config:clear
```

4. Run DB diagnostic command:

```bash
php artisan diagnostics:db-connection
```

It prints safe connection metadata (no password) and runs a `SELECT 1` ping.

## Prepare schema/data

Run migrations and seeders if needed:

```bash
php artisan migrate --seed
```

## Login trace (optional)

Enable temporary login/session tracing:

```env
LOGIN_TRACE=true
```

Then clear config cache:

```bash
php artisan config:clear
```
