<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class DashboardController extends Controller
{
    public function __invoke(): InertiaResponse
    {
        return Inertia::render('Support/Dashboard/Index', [
            'routes' => [
                'tickets' => route('support.support-tickets.index'),
            ],
        ]);
    }
}
