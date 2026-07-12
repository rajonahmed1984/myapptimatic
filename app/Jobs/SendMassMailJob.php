<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Models\MassMail;
use App\Models\Setting;
use App\Support\Branding;
use App\Services\Mail\MailSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMassMailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $timeout = 600;

    public function __construct(
        public readonly int $massMailId
    ) {
    }

    public function handle(MailSender $mailSender): void
    {
        $massMail = MassMail::find($this->massMailId);
        if (!$massMail || in_array($massMail->status, ['completed', 'sending'], true)) {
            return;
        }

        $massMail->update(['status' => 'sending']);

        $query = Customer::query();
        if ($massMail->target_status !== 'all') {
            $query->where('status', $massMail->target_status);
        }
        $customers = $query->whereNotNull('email')->where('email', '!=', '')->get();

        $massMail->update([
            'total_recipients' => $customers->count(),
        ]);

        $sentCount = 0;
        $companyName = Setting::getValue('company_name') ?: config('app.name');
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = \App\Support\UrlResolver::portalUrl();

        foreach ($customers as $customer) {
            try {
                $mailSender->sendView(
                    \App\Enums\MailCategory::SYSTEM,
                    $customer->email,
                    'emails.generic',
                    [
                        'bodyHtml' => $massMail->body,
                        'subject' => $massMail->subject,
                        'companyName' => $companyName,
                        'logoUrl' => $logoUrl,
                        'portalUrl' => $portalUrl,
                    ],
                    $massMail->subject
                );

                $sentCount++;
                $massMail->update(['sent_count' => $sentCount]);
            } catch (\Exception $e) {
                Log::error("Failed to send mass mail ID {$massMail->id} to {$customer->email}: " . $e->getMessage());
            }
        }

        $massMail->update(['status' => 'completed']);
    }
}
