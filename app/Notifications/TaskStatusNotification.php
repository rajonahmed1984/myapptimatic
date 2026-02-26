<?php

namespace App\Notifications;

use App\Enums\MailCategory;
use App\Mail\Concerns\UsesMailCategory;
use App\Models\Employee;
use App\Models\ProjectTask;
use App\Models\ProjectTaskSubtask;
use App\Models\SalesRepresentative;
use App\Notifications\Concerns\SkipsInvalidMailRoutes;
use App\Models\Setting;
use App\Support\Branding;
use App\Support\UrlResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\HtmlString;

abstract class TaskStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SerializesModels;
    use UsesMailCategory;
    use SkipsInvalidMailRoutes;

    protected ProjectTask $task;
    protected ?ProjectTaskSubtask $subtask;
    protected string $viewUrl;
    protected string $projectUrl;
    protected ?string $portalLoginUrl;
    protected ?string $portalLoginLabel;

    public function __construct(
        ProjectTask $task,
        ?ProjectTaskSubtask $subtask,
        string $viewUrl,
        string $projectUrl,
        ?string $portalLoginUrl,
        ?string $portalLoginLabel
    ) {
        $this->task = $task;
        $this->subtask = $subtask;
        $this->viewUrl = $viewUrl;
        $this->projectUrl = $projectUrl;
        $this->portalLoginUrl = $portalLoginUrl;
        $this->portalLoginLabel = $portalLoginLabel;
    }

    abstract protected function statusLabel(): string;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return true;
        }

        return $this->shouldDeliverMailTo($notifiable, 'task-status');
    }

    public function mailCategory(): string
    {
        return MailCategory::SYSTEM;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->task->loadMissing(['project', 'assignments.employee', 'assignments.salesRep', 'createdBy']);
        if ($this->subtask) {
            $this->subtask->loadMissing(['createdBy']);
        }

        $typeLabel = $this->subtask ? 'Subtask' : 'Task';
        $projectName = $this->task->project?->name
            ?? ($this->task->project_id ? 'Project #' . $this->task->project_id : 'Project');
        $title = $this->subtask?->title ?? $this->task->title ?? '--';
        $taskTitle = $this->task->title ?? '--';

        $creatorName = $this->resolveCreatorName();
        $assignees = $this->resolveAssignees();
        $dueDate = $this->resolveDueDate();

        $subject = sprintf(
            '%s %s: %s (%s)',
            $typeLabel,
            $this->statusLabel(),
            $title,
            $projectName
        );

        $projectLink = $this->projectUrl !== ''
            ? '<a href="' . e($this->projectUrl) . '">' . e($projectName) . '</a>'
            : e($projectName);
        $viewLink = $this->viewUrl !== ''
            ? '<a href="' . e($this->viewUrl) . '">View ' . e($typeLabel) . '</a>'
            : '';

        $lines = [
            '<strong>' . e($typeLabel) . ' ' . e($this->statusLabel()) . '</strong>',
            'Project: ' . $projectLink,
            'Task: ' . e($taskTitle),
        ];

        if ($this->subtask) {
            $lines[] = 'Subtask: ' . e($this->subtask->title ?? '--');
        }

        $lines[] = 'Status: ' . e($this->statusLabel());
        $lines[] = 'Created by: ' . e($creatorName);

        if ($dueDate !== null) {
            $lines[] = 'Due date: ' . e($dueDate);
        }

        if ($assignees !== null) {
            $lines[] = 'Assignees: ' . e($assignees);
        }

        if ($viewLink !== '') {
            $lines[] = $viewLink;
        }

        $bodyHtml = '<p>' . implode('<br>', $lines) . '</p>';

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
                'bodyHtml' => new HtmlString($bodyHtml),
            ]);

        return $this->withMailCategoryHeader($mail);
    }

    private function resolveCreatorName(): string
    {
        $creator = $this->subtask?->createdBy ?? $this->task->createdBy;
        if ($creator) {
            return $creator->name ?? 'System';
        }

        $creatorId = $this->subtask?->created_by ?? $this->task->created_by;
        if (! $creatorId) {
            return 'System';
        }

        $employee = Employee::find($creatorId);
        return $employee?->name ?? 'System';
    }

    private function resolveAssignees(): ?string
    {
        $names = $this->task->assignments
            ->map(fn ($assignment) => $assignment->assigneeName())
            ->filter()
            ->unique()
            ->values();

        if ($names->isNotEmpty()) {
            return $names->implode(', ');
        }

        if ($this->task->assigned_type === 'employee' && $this->task->assigned_id) {
            $employee = Employee::find($this->task->assigned_id);
            if ($employee?->name) {
                return $employee->name;
            }
        }

        if (in_array($this->task->assigned_type, ['sales_rep', 'salesrep'], true) && $this->task->assigned_id) {
            $salesRep = SalesRepresentative::find($this->task->assigned_id);
            if ($salesRep?->name) {
                return $salesRep->name;
            }
        }

        return null;
    }

    private function resolveDueDate(): ?string
    {
        $dateFormat = Setting::getValue('date_format', config('app.date_format', 'd-m-Y'));
        $date = $this->subtask?->due_date ?? $this->task->due_date;

        return $date?->format($dateFormat);
    }
}
