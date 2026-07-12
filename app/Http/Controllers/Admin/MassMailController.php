<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\MassMail;
use App\Jobs\SendMassMailJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MassMailController extends Controller
{
    public function index(): Response
    {
        $campaigns = MassMail::with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->through(function (MassMail $mail) {
                return [
                    'id' => $mail->id,
                    'subject' => $mail->subject,
                    'body' => $mail->body,
                    'target_status' => $mail->target_status,
                    'total_recipients' => $mail->total_recipients,
                    'sent_count' => $mail->sent_count,
                    'status' => $mail->status,
                    'creator_name' => $mail->creator?->name ?? 'System',
                    'created_at_display' => $mail->created_at->format('M d, Y h:i A'),
                ];
            });

        $counts = [
            'all' => Customer::whereNotNull('email')->where('email', '!=', '')->count(),
            'active' => Customer::where('status', 'active')->whereNotNull('email')->where('email', '!=', '')->count(),
            'inactive' => Customer::where('status', 'inactive')->whereNotNull('email')->where('email', '!=', '')->count(),
            'suspended' => Customer::where('status', 'suspended')->whereNotNull('email')->where('email', '!=', '')->count(),
        ];

        return Inertia::render('Admin/MassMail/Index', [
            'campaigns' => $campaigns,
            'counts' => $counts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'target_status' => 'required|string|in:all,active,inactive,suspended',
        ]);

        $massMail = MassMail::create([
            'subject' => $data['subject'],
            'body' => $data['body'],
            'target_status' => $data['target_status'],
            'status' => 'pending',
            'created_by' => auth()->id(),
        ]);

        SendMassMailJob::dispatch($massMail->id);

        return redirect()->route('admin.mass-mail.index')
            ->with('status', 'Mass mail campaign dispatched successfully.');
    }
}
