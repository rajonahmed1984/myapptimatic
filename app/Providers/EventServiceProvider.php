<?php

namespace App\Providers;

use App\Listeners\RecordEmployeeLogin;
use App\Listeners\RecordEmployeeLogout;
use App\Listeners\RecordUserLoginSession;
use App\Listeners\RecordUserLogoutSession;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [
        Login::class => [
            RecordEmployeeLogin::class,
            RecordUserLoginSession::class,
        ],
        Logout::class => [
            RecordEmployeeLogout::class,
            RecordUserLogoutSession::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
