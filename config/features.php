<?php

return [
    /*
    |--------------------------------------------------------------------------
    | UI Feature Flags
    |--------------------------------------------------------------------------
    |
    | React/Inertia routes are opt-in so Blade remains the default until
    | each module is proven equivalent and safe to switch.
    |
    */
    'react_sandbox' => (bool) env('FEATURE_REACT_SANDBOX', false),
    'admin_expenses_recurring_index' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_INDEX', false),
    'admin_expenses_recurring_show' => (bool) env('FEATURE_ADMIN_EXPENSES_RECURRING_SHOW', false),
];
