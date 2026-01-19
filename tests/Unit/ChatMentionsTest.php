<?php

namespace Tests\Unit;

use App\Support\ChatMentions;
use PHPUnit\Framework\TestCase;

class ChatMentionsTest extends TestCase
{
    public function test_normalize_mentions_from_message(): void
    {
        $mentionables = [
            ['type' => 'user', 'id' => 1, 'label' => 'Jane Doe'],
            ['type' => 'employee', 'id' => 2, 'label' => 'Alex'],
            ['type' => 'user', 'id' => 3, 'label' => 'Morgan'],
        ];

        $message = 'Hello @Jane Doe, meet @Alex.';

        $mentions = ChatMentions::normalize($message, $mentionables);

        $this->assertCount(2, $mentions);
        $this->assertTrue($this->mentionsContain($mentions, 'user', 1, 'Jane Doe'));
        $this->assertTrue($this->mentionsContain($mentions, 'employee', 2, 'Alex'));
    }

    public function test_submitted_mentions_are_filtered_by_message(): void
    {
        $mentionables = [
            ['type' => 'user', 'id' => 1, 'label' => 'Jane Doe'],
            ['type' => 'user', 'id' => 2, 'label' => 'Sam'],
        ];

        $submitted = [
            ['type' => 'user', 'id' => 1, 'label' => 'Jane Doe'],
            ['type' => 'user', 'id' => 2, 'label' => 'Sam'],
        ];

        $message = 'Only mentioning @Jane Doe here.';

        $mentions = ChatMentions::normalize($message, $mentionables, $submitted);

        $this->assertCount(1, $mentions);
        $this->assertTrue($this->mentionsContain($mentions, 'user', 1, 'Jane Doe'));
        $this->assertFalse($this->mentionsContain($mentions, 'user', 2, 'Sam'));
    }

    private function mentionsContain(array $mentions, string $type, int $id, string $label): bool
    {
        foreach ($mentions as $mention) {
            if (($mention['type'] ?? '') === $type
                && (int) ($mention['id'] ?? 0) === $id
                && ($mention['label'] ?? '') === $label) {
                return true;
            }
        }

        return false;
    }
}
