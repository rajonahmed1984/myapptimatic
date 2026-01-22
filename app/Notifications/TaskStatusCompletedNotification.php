<?php

namespace App\Notifications;

class TaskStatusCompletedNotification extends TaskStatusNotification
{
    protected function statusLabel(): string
    {
        return 'Completed';
    }
}
