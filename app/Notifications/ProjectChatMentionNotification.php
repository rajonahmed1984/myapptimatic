<?php

namespace App\Notifications;

use App\Models\Project;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ProjectChatMentionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Project $project,
        private readonly string $authorName,
        private readonly string $snippet,
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
        $subject = sprintf('You were mentioned in project chat: %s', $projectName);

        $lines = [
            '<strong>' . e($this->authorName) . '</strong> mentioned you in project chat.',
            'Project: ' . e($projectName),
            'Message: ' . e($this->snippet !== '' ? $this->snippet : 'Attachment'),
            'Time: ' . e((string) now()->format('Y-m-d H:i')),
        ];

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

