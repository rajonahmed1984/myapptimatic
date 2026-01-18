<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SystemCacheController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $commands = ['cache:clear', 'route:clear', 'config:clear', 'view:clear', 'optimize:clear'];

        foreach ($commands as $command) {
            Artisan::call($command);
        }

        return back()->with([
            'status' => 'System caches refreshed. Browser storage is being purged and the page will reload shortly.',
            'cache_cleared' => true,
        ]);
    }
}
