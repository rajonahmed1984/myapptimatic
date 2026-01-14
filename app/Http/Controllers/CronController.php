<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class CronController extends Controller
{
    public function billing(Request $request)
    {
        $token = (string) $request->input('token', '');
        $expected = (string) Setting::getValue('cron_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response('Unauthorized', 403);
        }

        Artisan::call('billing:run');

        return view('cron.billing', [
            'message' => 'Cron job executed successfully.',
            'ran_at' => now()->toDateTimeString(),
        ]);
    }
}
