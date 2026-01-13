<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\SalesRepresentative;

class TaskAssignees
{
    public static function parse(array $assignees): array
    {
        $parsed = [];

        foreach ($assignees as $entry) {
            if (! is_string($entry) || $entry === '') {
                continue;
            }

            [$type, $id] = array_pad(explode(':', $entry, 2), 2, null);
            $id = $id ? (int) $id : null;

            if (! $type || ! $id) {
                continue;
            }

            if (! in_array($type, ['employee', 'sales_rep'], true)) {
                continue;
            }

            if ($type === 'employee' && ! Employee::whereKey($id)->exists()) {
                continue;
            }

            if ($type === 'sales_rep' && ! SalesRepresentative::whereKey($id)->exists()) {
                continue;
            }

            $parsed[] = ['type' => $type, 'id' => $id];
        }

        return $parsed;
    }

    public static function toStrings(array $assignees): array
    {
        return array_map(fn ($item) => $item['type'] . ':' . $item['id'], $assignees);
    }
}
