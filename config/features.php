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
];
