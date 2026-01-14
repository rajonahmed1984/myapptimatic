<?php

namespace App\Providers;

use App\Listeners\RecordEmployeeLogin;
use App\Listeners\RecordEmployeeLogout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /** @var array<class-string, array<int, class-string>> */
    protected $listen = [
        Login::class => [
            RecordEmployeeLogin::class,
        ],
        Logout::class => [
            RecordEmployeeLogout::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
