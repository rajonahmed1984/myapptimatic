<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClientRequestController extends Controller
{
    public function index()
    {
        $requests = ClientRequest::query()
            ->with([
                'customer',
                'user',
                'invoice',
                'subscription.plan.product',
                'licenseDomain.license.product',
            ])
            ->latest()
            ->get();

        return view('admin.requests.index', [
            'requests' => $requests,
        ]);
    }

    public function update(Request $request, ClientRequest $clientRequest)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['pending', 'approved', 'rejected', 'completed'])],
        ]);

        $clientRequest->update([
            'status' => $data['status'],
        ]);

        return back()->with('status', 'Request updated.');
    }
}
