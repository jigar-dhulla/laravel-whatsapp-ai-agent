<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\DB;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class WhatsAppMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWacliDatabase();
    }

    public function test_quotes_own_message_when_quoted_message_is_outbound(): void
    {
        $this->seedMessage(['rowid' => 1, 'msg_id' => 'BOT1', 'from_me' => 1, 'text' => 'I am the agent']);
        $this->seedMessage(['rowid' => 2, 'msg_id' => 'USER1', 'from_me' => 0, 'text' => 'thanks!', 'quoted_msg_id' => 'BOT1']);

        $this->assertTrue(WhatsAppMessage::query()->find(2)->quotesOwnMessage());
    }

    public function test_does_not_quote_own_message_when_quoting_another_participant(): void
    {
        $this->seedMessage(['rowid' => 1, 'msg_id' => 'USER0', 'from_me' => 0, 'text' => 'original']);
        $this->seedMessage(['rowid' => 2, 'msg_id' => 'USER1', 'from_me' => 0, 'text' => 'reply', 'quoted_msg_id' => 'USER0']);

        $this->assertFalse(WhatsAppMessage::query()->find(2)->quotesOwnMessage());
    }

    public function test_does_not_quote_own_message_when_not_a_reply(): void
    {
        $this->seedMessage(['rowid' => 1, 'msg_id' => 'USER1', 'from_me' => 0, 'text' => 'plain message']);

        $this->assertFalse(WhatsAppMessage::query()->find(1)->quotesOwnMessage());
    }

    public function test_does_not_quote_own_message_when_quoted_id_is_unknown(): void
    {
        $this->seedMessage(['rowid' => 1, 'msg_id' => 'USER1', 'from_me' => 0, 'text' => 'reply', 'quoted_msg_id' => 'GONE']);

        $this->assertFalse(WhatsAppMessage::query()->find(1)->quotesOwnMessage());
    }

    private function seedMessage(array $attrs): void
    {
        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert(array_merge([
            'chat_jid' => '111@s.whatsapp.net',
            'chat_name' => 'Chat',
            'sender_jid' => '111@s.whatsapp.net',
            'sender_name' => 'Sender',
            'ts' => 1700000000,
            'from_me' => 0,
            'display_text' => null,
            'media_type' => null,
            'quoted_msg_id' => null,
            'quoted_sender_jid' => null,
        ], $attrs));
    }
}
