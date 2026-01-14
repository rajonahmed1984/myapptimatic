<?php

namespace App\Providers;

use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class ActivityTrackingEventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        Login::class => [
            \App\Listeners\RecordUserLoginSession::class,
        ],
        Logout::class => [
            \App\Listeners\RecordUserLogoutSession::class,
        ],
    ];
}
