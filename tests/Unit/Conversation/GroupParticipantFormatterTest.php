<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Unit\Conversation;

use JigarDhulla\LaravelWhatsApp\Conversation\GroupParticipantFormatter;
use PHPUnit\Framework\TestCase;

class GroupParticipantFormatterTest extends TestCase
{
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

        $this->assertNull($formatter->senderLabel('111@s.whatsapp.net', 'Alice', '111@s.whatsapp.net'));
    }

    public function test_sender_label_uses_sender_name_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[Alice]', $formatter->senderLabel('999@g.us', 'Alice', 'alice@s.whatsapp.net'));
    }

    public function test_sender_label_falls_back_to_phone_when_name_is_null(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[12345]', $formatter->senderLabel('999@g.us', null, '12345@s.whatsapp.net'));
    }

    public function test_sender_label_falls_back_to_unknown_when_both_are_null(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[Unknown]', $formatter->senderLabel('999@g.us', null, null));
    }

    public function test_sender_label_treats_empty_sender_name_as_missing(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[12345]', $formatter->senderLabel('999@g.us', '', '12345@s.whatsapp.net'));
    }

    public function test_sender_label_falls_back_to_unknown_when_jid_has_no_user_part(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[Unknown]', $formatter->senderLabel('999@g.us', null, '@s.whatsapp.net'));
        $this->assertSame('[Unknown]', $formatter->senderLabel('999@g.us', '', ''));
    }

    public function test_prefix_leaves_content_unchanged_in_dms(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('hello', $formatter->prefix('111@s.whatsapp.net', 'Alice', 'alice@s.whatsapp.net', 'hello'));
    }

    public function test_prefix_prepends_label_in_groups(): void
    {
        $formatter = new GroupParticipantFormatter;

        $this->assertSame('[Bob] hello', $formatter->prefix('999@g.us', 'Bob', 'bob@s.whatsapp.net', 'hello'));
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
