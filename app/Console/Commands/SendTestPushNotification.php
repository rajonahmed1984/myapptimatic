<?php

namespace App\Console\Commands;

use App\Models\PushDevice;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'push:test
        {user : User id or email to notify}
        {--title=Test notification : Notification title}
        {--body=Push notifications are working. : Notification text}
        {--url= : Portal path to open when the notification is tapped}';

    protected $description = 'Send a test push notification to every app install a user is signed in on';

    public function handle(PushNotificationService $push): int
    {
        if (! $push->isConfigured()) {
            $this->error('Firebase is not configured. Put the service account key at '.config('services.fcm.credentials').' or set FIREBASE_CREDENTIALS.');

            return self::FAILURE;
        }

        $needle = (string) $this->argument('user');
        $user = User::query()
            ->when(ctype_digit($needle), fn ($query) => $query->whereKey((int) $needle), fn ($query) => $query->where('email', $needle))
            ->first();

        if (! $user) {
            $this->error("No user found for [{$needle}].");

            return self::FAILURE;
        }

        $devices = PushDevice::query()->where('user_id', $user->id);
        if (! $devices->exists()) {
            $this->warn("{$user->email} has no registered app install. Sign in to the mobile app as this user and allow notifications first.");

            return self::FAILURE;
        }

        $result = $push->sendNow(
            $devices,
            (string) $this->option('title'),
            (string) $this->option('body'),
            $this->option('url') ?: null
        );

        $this->info("Sent: {$result['sent']}, stale tokens removed: {$result['invalid']}, failed: {$result['failed']}");

        return $result['sent'] > 0 ? self::SUCCESS : self::FAILURE;
    }
}
