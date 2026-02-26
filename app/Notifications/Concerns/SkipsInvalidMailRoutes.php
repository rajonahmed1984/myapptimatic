<?php

namespace App\Notifications\Concerns;

use Illuminate\Support\Facades\Log;

trait SkipsInvalidMailRoutes
{
    protected function shouldDeliverMailTo(object $notifiable, string $context): bool
    {
        $email = strtolower(trim((string) $notifiable->routeNotificationFor('mail', $this)));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $suppressed = collect((array) config('system_mail.suppressed_recipients', []))
            ->map(fn ($item) => strtolower(trim((string) $item)))
            ->filter()
            ->all();

        if (in_array($email, $suppressed, true)) {
            Log::info('Suppressed queued notification email.', [
                'email' => $email,
                'context' => $context,
            ]);

            return false;
        }

        return true;
    }
}

