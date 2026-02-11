<?php

namespace App\Services;

use App\Models\SupportTicket;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupportTicketAiService
{
    public function analyze(SupportTicket $ticket, GeminiService $geminiService): array
    {
        $prompt = $this->buildPrompt($ticket);
        $raw = $geminiService->generateText($prompt);
        $data = $this->extractJson($raw);

        return [
            'raw' => $raw,
            'data' => $data,
        ];
    }

    private function buildPrompt(SupportTicket $ticket): string
    {
        $ticket->loadMissing(['customer', 'replies.user']);

        $replies = $ticket->replies
            ? $ticket->replies->sortBy('created_at')->values()
            : collect();

        if ($replies->count() > 12) {
            $replies = $replies->slice($replies->count() - 12)->values();
        }

        $conversation = $replies->map(function ($reply) {
            $message = (string) ($reply->message ?? '');
            $message = Str::squish($message);
            if (strlen($message) > 1200) {
                $message = Str::limit($message, 1200, '...');
            }

            return [
                'role' => $reply->is_admin ? 'support' : 'customer',
                'at' => $reply->created_at?->format('Y-m-d H:i'),
                'message' => $message,
            ];
        })->all();

        $payload = [
            'ticket' => [
                'id' => $ticket->id,
                'subject' => (string) $ticket->subject,
                'priority' => (string) $ticket->priority,
                'status' => (string) $ticket->status,
                'opened_at' => $ticket->created_at?->format('Y-m-d H:i'),
                'last_reply_at' => $ticket->last_reply_at?->format('Y-m-d H:i'),
                'customer' => [
                    'name' => $ticket->customer?->name,
                ],
            ],
            'conversation' => $conversation,
        ];

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are a senior support lead. Analyze the ticket and create an actionable response.

Rules:
- Use only the provided data. Do not invent facts.
- Output MUST be strict JSON (no code fences) with the following keys:
  summary, category, urgency, sentiment, suggested_reply, next_steps
- summary: 2-3 sentences
- category: short label like billing / technical / account / access / other
- urgency: low / medium / high
- sentiment: calm / neutral / frustrated / urgent
- suggested_reply: a polite reply in Bengali
- next_steps: array of 2-4 bullet strings

Ticket data (JSON):
{$json}
PROMPT;
    }

    private function extractJson(string $text): ?array
    {
        $clean = trim($text);

        if (Str::startsWith($clean, '```')) {
            $clean = preg_replace('/^```[a-zA-Z]*\n|```$/m', '', $clean);
            $clean = trim((string) $clean);
        }

        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }

        $json = substr($clean, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        return [
            'summary' => Arr::get($decoded, 'summary'),
            'category' => Arr::get($decoded, 'category'),
            'urgency' => Arr::get($decoded, 'urgency'),
            'sentiment' => Arr::get($decoded, 'sentiment'),
            'suggested_reply' => Arr::get($decoded, 'suggested_reply'),
            'next_steps' => Arr::get($decoded, 'next_steps'),
        ];
    }
}