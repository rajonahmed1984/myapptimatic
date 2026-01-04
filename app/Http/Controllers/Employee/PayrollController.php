<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\PayrollItem;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->attributes->get('employee');

        $items = PayrollItem::query()
            ->where('employee_id', $employee->id)
            ->with('period')
            ->orderByDesc('id')
            ->paginate(15);

        return view('employee.payroll.index', compact('items'));
    }
}
