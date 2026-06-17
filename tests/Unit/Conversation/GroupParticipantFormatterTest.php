<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Unit\Conversation;

use Illuminate\Support\Carbon;
use JigarDhulla\LaravelWhatsApp\Conversation\GroupParticipantFormatter;
use PHPUnit\Framework\TestCase;

class GroupParticipantFormatterTest extends TestCase
{
    private function datetime(): Carbon
    {
        return Carbon::create(2025, 12, 4, 13, 0, 0);
    }

    public function test_is_group_chat_detects_g_us_suffix(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertTrue($formatter->isGroupChat('999@g.us'));
        $this->assertFalse($formatter->isGroupChat('111@s.whatsapp.net'));
        $this->assertFalse($formatter->isGroupChat(null));
    }

    public function test_sender_label_returns_null_in_dms(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertNull($formatter->senderLabel('111@s.whatsapp.net', 'Alice', '111@s.whatsapp.net', $this->datetime()));
    }

    public function test_sender_label_uses_sender_name_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[Alice@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', 'Alice', 'alice@s.whatsapp.net', $this->datetime())
        );
    }

    public function test_sender_label_appends_datetime_to_label(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[Bob@2024-01-15 09:30:45]',
            $formatter->senderLabel('999@g.us', 'Bob', 'bob@s.whatsapp.net', Carbon::create(2024, 1, 15, 9, 30, 45))
        );
    }

    public function test_sender_label_falls_back_to_phone_when_name_is_null(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[12345@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', null, '12345@s.whatsapp.net', $this->datetime())
        );
    }

    public function test_sender_label_falls_back_to_unknown_when_both_are_null(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[Unknown@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', null, null, $this->datetime())
        );
    }

    public function test_sender_label_treats_empty_sender_name_as_missing(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[12345@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', '', '12345@s.whatsapp.net', $this->datetime())
        );
    }

    public function test_sender_label_falls_back_to_unknown_when_jid_has_no_user_part(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[Unknown@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', null, '@s.whatsapp.net', $this->datetime())
        );
        $this->assertSame(
            '[Unknown@2025-12-04 13:00:00]',
            $formatter->senderLabel('999@g.us', '', '', $this->datetime())
        );
    }

    public function test_prefix_leaves_content_unchanged_in_dms(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            'hello',
            $formatter->prefix('111@s.whatsapp.net', 'Alice', 'alice@s.whatsapp.net', $this->datetime(), 'hello')
        );
    }

    public function test_prefix_prepends_label_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame(
            '[Bob@2025-12-04 13:00:00] hello',
            $formatter->prefix('999@g.us', 'Bob', 'bob@s.whatsapp.net', $this->datetime(), 'hello')
        );
    }

    public function test_context_note_is_null_in_dms(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertNull($formatter->contextNote('111@s.whatsapp.net'));
        $this->assertNull($formatter->contextNote(null));
    }

    public function test_context_note_describes_prefix_convention_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $note = $formatter->contextNote('999@g.us');

        $this->assertNotNull($note);
        $this->assertStringContainsString('group chat', $note);
        $this->assertStringContainsString('square brackets', $note);
        $this->assertStringContainsString('datetime', $note);
    }

    public function test_context_ack_is_null_in_dms(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertNull($formatter->contextAck('111@s.whatsapp.net'));
        $this->assertNull($formatter->contextAck(null));
    }

    public function test_context_ack_returns_acknowledgement_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('Understood.', $formatter->contextAck('999@g.us'));
    }
}
