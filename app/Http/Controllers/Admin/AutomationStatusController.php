<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AutomationStatusService;

class AutomationStatusController extends Controller
{
    public function __construct(private AutomationStatusService $statusService)
    {
    }

    public function index()
    {
        return view('admin.automation-status', $this->statusService->getStatusPayload());
    }
}
