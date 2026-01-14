# Currency Feature - Quick Reference

## Files Modified/Created

### Created Files
1. **app/Support/Currency.php** - Currency helper with allowed currencies validation
2. **app/Services/CurrencyConverterService.php** - BDT ↔ USD converter service
3. **database/migrations/2026_02_20_000000_add_currency_to_users_table.php** - Add currency column to users
4. **tests/Feature/CurrencySelectionTest.php** - 13 comprehensive tests (all passing)
5. **CURRENCY_FEATURE_IMPLEMENTATION.md** - Full implementation documentation

### Modified Files
1. **app/Models/User.php** - Added `currency` to fillable
2. **app/Http/Controllers/AuthController.php** - Added currency validation in register()
3. **resources/views/auth/register.blade.php** - Added currency dropdown with BDT/USD options
4. **app/Services/PaymentService.php** - Validate currency, default to BDT
5. **app/Services/MilestoneInvoiceService.php** - Validate currency, default to BDT

## Key Implementation Details

### Allowed Currencies
- **BDT** (Bangladesh Taka) - Symbol: ৳ - DEFAULT
- **USD** (United States Dollar) - Symbol: $

### Database Changes
```sql
ALTER TABLE users ADD COLUMN currency ENUM('BDT', 'USD') DEFAULT 'BDT' AFTER role;
```

### Exchange Rates (Static)
- 1 BDT = 0.0095 USD
- 1 USD = 105.50 BDT

## Usage Examples

### Using Currency Helper
```php
use App\Support\Currency;

// Check if currency is allowed
Currency::isAllowed('BDT');        // true
Currency::isAllowed('EUR');        // false

// Get currency symbol
Currency::symbol('BDT');           // "৳"
Currency::symbol('USD');           // "$"

// Format currency
Currency::format(1000, 'BDT');    // "৳1,000.00"
Currency::format(100.50, 'USD');  // "$100.50"

// Get default currency
Currency::default();               // "BDT"

// Get allowed currencies
Currency::allowed();               // ['BDT', 'USD']
```

### Using Currency Converter
```php
use App\Services\CurrencyConverterService;

$converter = new CurrencyConverterService();

// Convert amount
$usdAmount = $converter->convert(1000, 'BDT', 'USD');  // 9.5
$bdtAmount = $converter->convert(100, 'USD', 'BDT');   // 10550

// Get exchange rate
$rate = $converter->getRate('BDT', 'USD');  // 0.0095
$rate = $converter->getRate('USD', 'BDT');  // 105.50

// Validate currency
$converter->validateCurrency('BDT');  // true
$converter->validateCurrency('EUR');  // false

// Get allowed currencies
$converter->getAllowedCurrencies();  // ['BDT', 'USD']
```

### User Registration with Currency
```php
// Frontend - Form submits currency field
// POST /register with currency=BDT or currency=USD

// Backend validation in AuthController
'currency' => ['required', 'in:BDT,USD']

// User gets created with currency
User::create([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => bcrypt('password'),
    'currency' => 'USD',  // BDT or USD only
]);
```

## Test Coverage

All 13 tests are passing:

1. ✅ User registration with BDT (default)
2. ✅ User registration with USD
3. ✅ Registration requires currency field
4. ✅ Registration rejects invalid currencies
5. ✅ Only BDT and USD are allowed
6. ✅ Currency helper validates correctly
7. ✅ Currency symbols display correctly
8. ✅ Currency formatting works
9. ✅ Converter only allows BDT/USD
10. ✅ Converter rejects invalid source
11. ✅ Converter rejects invalid target
12. ✅ Conversion rates are accurate
13. ✅ getRate method works correctly

## Error Handling

### Invalid Currency on Registration
```
ValidationException: currency field must be BDT or USD
```

### Invalid Currency in Converter
```
InvalidArgumentException: Currency 'EUR' is not allowed. Only BDT and USD are supported.
```

### Default Fallback
Services automatically default to BDT if:
- Currency is missing
- Currency is invalid
- Currency is not allowed

## Migration Status
✅ Migration applied successfully: `2026_02_20_000000_add_currency_to_users_table`
- Column added to users table
- ENUM type ensures only BDT/USD can be stored
- Default value: BDT

## Total Test Results
```
Tests: 35 passed (94 assertions)
Duration: 21.52s

Currency Tests: 13/13 ✅
Other Tests: 22/22 ✅
```
