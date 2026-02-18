<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ProjectChatSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly int $newMessageCount,
        private readonly array $summaryLines,
        private readonly ?string $chatUrl,
        private readonly ?string $portalLoginUrl,
        private readonly ?string $portalLoginLabel
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $companyName = Setting::getValue('company_name', config('app.name'));
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();

        $projectName = $this->project->name ?: ('Project #' . $this->project->id);
        $subject = sprintf('New chat activity in %s (%d)', $projectName, $this->newMessageCount);

        $lines = [
            '<strong>New project chat messages</strong>',
            'Project: ' . e($projectName),
            'New messages: ' . e((string) $this->newMessageCount),
        ];

        if (! empty($this->summaryLines)) {
            $lines[] = '<br><strong>Summary</strong>';
            foreach ($this->summaryLines as $line) {
                $lines[] = '• ' . e((string) $line);
            }
        }

        if ($this->chatUrl) {
            $lines[] = '<br><a href="' . e($this->chatUrl) . '">Open project chat</a>';
        }

        return (new MailMessage())
            ->subject($subject)
            ->view('emails.generic', [
                'subject' => $subject,
                'companyName' => $companyName,
                'logoUrl' => $logoUrl,
                'portalUrl' => $portalUrl,
                'portalLoginUrl' => $this->portalLoginUrl,
                'portalLoginLabel' => $this->portalLoginLabel,
                'bodyHtml' => new HtmlString('<p>' . implode('<br>', $lines) . '</p>'),
            ]);
    }
}
