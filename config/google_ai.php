<?php

return [
    'enabled' => (bool) env('GOOGLE_AI_ENABLED', true),
    'api_key' => env('GOOGLE_AI_API_KEY'),
    'model' => env('GOOGLE_AI_MODEL', 'gemini-2.5-flash'),
    'base_url' => env('GOOGLE_AI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
    'timeout' => (int) env('GOOGLE_AI_TIMEOUT', 30),
    'max_output_tokens' => (int) env('GOOGLE_AI_MAX_OUTPUT_TOKENS', 1200),
    'temperature' => (float) env('GOOGLE_AI_TEMPERATURE', 0.4),
    'thinking_budget' => (int) env('GOOGLE_AI_THINKING_BUDGET', 0),
];
