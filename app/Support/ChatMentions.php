<?php

namespace App\Support;

class ChatMentions
{
    public static function normalize(?string $message, array $mentionables, ?array $submittedMentions = null): array
    {
        $message = $message ?? '';
        $mentionableMap = [];

        foreach ($mentionables as $mentionable) {
            $type = strtolower((string) ($mentionable['type'] ?? ''));
            $id = (int) ($mentionable['id'] ?? 0);
            $label = trim((string) ($mentionable['label'] ?? ''));

            if ($type === '' || $id <= 0 || $label === '') {
                continue;
            }

            $mentionableMap[$type . ':' . $id] = [
                'type' => $type,
                'id' => $id,
                'label' => $label,
            ];
        }

        if (empty($mentionableMap) || $message === '') {
            return [];
        }

        $normalized = [];

        if (is_array($submittedMentions)) {
            foreach ($submittedMentions as $mention) {
                if (! is_array($mention)) {
                    continue;
                }

                $type = strtolower((string) ($mention['type'] ?? ''));
                $id = (int) ($mention['id'] ?? 0);
                $key = $type . ':' . $id;

                if (! isset($mentionableMap[$key])) {
                    continue;
                }

                $label = trim((string) ($mention['label'] ?? $mentionableMap[$key]['label']));
                if ($label === '') {
                    $label = $mentionableMap[$key]['label'];
                }

                if (! self::messageContainsMention($message, $label)) {
                    continue;
                }

                $normalized[$key] = [
                    'type' => $type,
                    'id' => $id,
                    'label' => $label,
                ];
            }
        }

        if (! empty($normalized)) {
            return array_values($normalized);
        }

        $sortedMentionables = array_values($mentionableMap);
        usort($sortedMentionables, function ($left, $right) {
            return mb_strlen($right['label']) <=> mb_strlen($left['label']);
        });

        foreach ($sortedMentionables as $mentionable) {
            if (self::messageContainsMention($message, $mentionable['label'])) {
                $key = $mentionable['type'] . ':' . $mentionable['id'];
                $normalized[$key] = $mentionable;
            }
        }

        return array_values($normalized);
    }

    public static function messageContainsMention(string $message, string $label): bool
    {
        $label = trim($label);
        if ($label === '') {
            return false;
        }

        $escaped = preg_quote($label, '/');
        $pattern = '/(^|\s)@' . $escaped . '(?=\s|$|[[:punct:]])/iu';

        return (bool) preg_match($pattern, $message);
    }
}
