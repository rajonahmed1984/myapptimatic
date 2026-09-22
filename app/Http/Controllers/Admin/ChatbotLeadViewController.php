<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLead;
use App\Support\PaginationPayload;
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
                'is_read' => (bool) $lead->is_read,
                'read_at' => $lead->read_at?->toIso8601String(),
                'created_at_display' => $lead->created_at->timezone('Asia/Dhaka')->format('M d, Y h:i A'),
                'created_at' => $lead->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('Admin/ChatbotLeads/Index', [
            'pageTitle' => 'Chatbot Leads',
            'leads' => $leads->items(),
            'unreadCount' => ChatbotLead::where('is_read', false)->count(),
            'filters' => [
                'search' => $search,
            ],
            'pagination' => PaginationPayload::make($leads),
            'routes' => [
                'index' => route('admin.chatbot-leads.index'),
                'destroy' => route('admin.chatbot-leads.destroy', ':id'),
                'toggle_read' => route('admin.chatbot-leads.toggle-read', ':id'),
                'mark_all_read' => route('admin.chatbot-leads.mark-all-read'),
            ],
        ]);
    }

    public function toggleRead($id)
    {
        $lead = ChatbotLead::findOrFail($id);
        $lead->is_read = ! $lead->is_read;
        $lead->read_at = $lead->is_read ? now() : null;
        $lead->save();

        return back()->with('status', $lead->is_read ? 'Lead marked as read.' : 'Lead marked as unread.');
    }

    public function markAllRead()
    {
        ChatbotLead::where('is_read', false)->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return back()->with('status', 'All chatbot leads marked as read.');
    }

    public function destroy($id)
    {
        $lead = ChatbotLead::findOrFail($id);
        $lead->delete();

        return redirect()->route('admin.chatbot-leads.index')
            ->with('status', 'Chatbot lead deleted successfully.');
    }
}
