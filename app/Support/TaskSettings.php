<?php

namespace App\Support;

use App\Models\Setting;

class TaskSettings
{
    public const TYPE_LABELS = [
        'bug' => 'Bug',
        'feature' => 'Feature',
        'support' => 'Support',
        'design' => 'Design',
        'upload' => 'Upload / Document',
        'custom' => 'Custom',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    public static function enabledTaskTypes(): array
    {
        $stored = Setting::getValue('task_types_enabled');
        $types = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($types) || empty($types)) {
            return array_keys(self::TYPE_LABELS);
        }

        return array_values(array_intersect($types, array_keys(self::TYPE_LABELS)));
    }

    public static function taskTypeOptions(): array
    {
        $enabled = self::enabledTaskTypes();
        $customLabel = trim((string) Setting::getValue('task_custom_type_label'));

        $labels = self::TYPE_LABELS;
        if ($customLabel !== '') {
            $labels['custom'] = $customLabel;
        }

        return array_filter($labels, fn ($key) => in_array($key, $enabled, true), ARRAY_FILTER_USE_KEY);
    }

    public static function priorityOptions(): array
    {
        return self::PRIORITIES;
    }

    public static function uploadMaxMb(): int
    {
        $value = (int) Setting::getValue('task_upload_max_mb', 10);
        return $value > 0 ? $value : 10;
    }

    public static function defaultCustomerVisible(): bool
    {
        return (bool) Setting::getValue('task_customer_visible_default', false);
    }
}
