<?php

namespace App\Notifications;

class TaskStatusOpenedNotification extends TaskStatusNotification
{
    protected function statusLabel(): string
    {
        return 'Open';
    }
}
