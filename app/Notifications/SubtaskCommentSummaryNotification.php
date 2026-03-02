<?php

namespace App\Notifications;

use App\Enums\MailCategory;
use App\Mail\Concerns\UsesMailCategory;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\Setting;
use App\Notifications\Concerns\SkipsInvalidMailRoutes;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SubtaskCommentSummaryNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesMailCategory;
    use SkipsInvalidMailRoutes;

    public function __construct(
        private readonly ProjectTask $task,
        private readonly ProjectTaskSubtask $subtask,
        private readonly string $actorName,
        private readonly array $summaryLines,
        private readonly ?string $viewUrl,
        private readonly ?string $portalLoginUrl,
        private readonly ?string $portalLoginLabel
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return true;
        }

        return $this->shouldDeliverMailTo($notifiable, 'subtask-comment-summary');
    }

    public function mailCategory(): string
    {
        return MailCategory::SUPPORT;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $projectName = (string) ($this->task->project?->name ?: ('Project #' . $this->task->project_id));
        $taskTitle = (string) ($this->task->title ?: 'Task');
        $subtaskTitle = (string) ($this->subtask->title ?: 'Subtask');
        $subject = sprintf('Subtask comment update: %s (%s)', $subtaskTitle, $projectName);

        $lines = [
            '<strong>New subtask comment</strong>',
            'Project: ' . e($projectName),
            'Task: ' . e($taskTitle),
            'Subtask: ' . e($subtaskTitle),
            'Latest by: ' . e($this->actorName),
        ];

        if (! empty($this->summaryLines)) {
            $lines[] = '<br><strong>Comment summary</strong>';
            foreach ($this->summaryLines as $line) {
                $lines[] = '&bull; ' . e((string) $line);
            }
        }

        if ($this->viewUrl) {
            $lines[] = '<br><a href="' . e($this->viewUrl) . '">Open task details</a>';
        }

        $companyName = Setting::getValue('company_name', config('app.name'));
        $logoUrl = Branding::url(Setting::getValue('company_logo_path'));
        $portalUrl = UrlResolver::portalUrl();

        $mail = (new MailMessage())
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

        return $this->withMailCategoryHeader($mail);
    }
}
