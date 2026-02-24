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
    'react_public_products' => (bool) env('FEATURE_REACT_PUBLIC_PRODUCTS', false),
    'admin_affiliate_commissions_index' => (bool) env('FEATURE_ADMIN_AFFILIATE_COMMISSIONS_INDEX', false),
    'admin_affiliate_payouts_ui' => (bool) env('FEATURE_ADMIN_AFFILIATE_PAYOUTS_UI', false),
];
