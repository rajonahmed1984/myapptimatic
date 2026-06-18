<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatbotLead;
use Illuminate\Http\Request;

class ChatbotLeadController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'product_interest' => ['nullable', 'string', 'max:255'],
            'transcript' => ['nullable', 'string'],
        ]);

        $lead = ChatbotLead::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'product_interest' => $data['product_interest'] ?? null,
            'transcript' => $data['transcript'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Chat history synced successfully to your portal.',
            'lead_id' => $lead->id,
        ], 201);
    }
}
