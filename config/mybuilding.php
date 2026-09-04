<?php

return [
    /*
    | Product slug used to recognise MyBuilding licences.
    */
    'product_slug' => env('MYBUILDING_PRODUCT_SLUG', 'mybuilding'),

    /*
    | Shared secret used to sign provisioning calls into a customer's
    | MyBuilding installation. Must match APPTIMATIC_REGISTRATION_SECRET there.
    */
    'provision_secret' => env('MYBUILDING_PROVISION_SECRET', ''),

    /*
    | Default installation URL offered when creating a provision record.
    */
    'default_install_url' => env('MYBUILDING_DEFAULT_INSTALL_URL', ''),

    'timeout' => (int) env('MYBUILDING_TIMEOUT', 20),
];
