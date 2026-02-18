<?php

return [
    'enabled' => env('PROJECT_CHAT_EMAIL_NOTIFICATIONS', true),
    'project_limit_per_run' => (int) env('PROJECT_CHAT_EMAIL_PROJECT_LIMIT', 100),
    'summary_lines' => (int) env('PROJECT_CHAT_EMAIL_SUMMARY_LINES', 5),
];
