<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLead;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ChatbotLeadViewController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = ChatbotLead::query()->orderBy('created_at', 'desc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('product_interest', 'like', "%{$search}%");
            });
        }

        $leads = $query->paginate(20)->withQueryString();

        // Transform collection to format dates
        $leads->getCollection()->transform(function ($lead) {
            return [
                'id' => $lead->id,
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone ?? 'N/A',
                'product_interest' => $lead->product_interest ?? 'General',
                'transcript' => $lead->transcript ?? '',
                'created_at_display' => $lead->created_at->timezone('Asia/Dhaka')->format('M d, Y h:i A'),
                'created_at' => $lead->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/ChatbotLeads/Index', [
            'pageTitle' => 'Chatbot Leads',
            'leads' => $leads->items(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => [
                'has_pages' => $leads->hasPages(),
                'previous_url' => $leads->previousPageUrl(),
                'next_url' => $leads->nextPageUrl(),
            ],
            'routes' => [
                'index' => route('admin.chatbot-leads.index'),
                'destroy' => route('admin.chatbot-leads.destroy', ':id'),
            ]
        ]);
    }

    public function destroy($id)
    {
        $lead = ChatbotLead::findOrFail($id);
        $lead->delete();

        return redirect()->route('admin.chatbot-leads.index')
            ->with('status', 'Chatbot lead deleted successfully.');
    }
}
