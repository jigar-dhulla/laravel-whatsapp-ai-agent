<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\DB;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Conversation\GroupParticipantFormatter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

class RemembersWhatsAppConversationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWacliDatabase();
    }

    public function test_messages_are_returned_in_chronological_order(): void
    {
        $this->seedMessage(['rowid' => 1, 'ts' => 1700000000, 'from_me' => 0, 'text' => 'first user']);
        $this->seedMessage(['rowid' => 2, 'ts' => 1700000010, 'from_me' => 1, 'text' => 'first reply']);
        $this->seedMessage(['rowid' => 3, 'ts' => 1700000020, 'from_me' => 0, 'text' => 'second user']);

        $agent = new WhatsAppAgent;
        $agent->forChat('111@s.whatsapp.net', '111@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertCount(3, $messages);
        $this->assertSame('first user', $messages[0]->content);
        $this->assertSame(MessageRole::User, $messages[0]->role);
        $this->assertSame('first reply', $messages[1]->content);
        $this->assertSame(MessageRole::Assistant, $messages[1]->role);
        $this->assertSame('second user', $messages[2]->content);
        $this->assertSame(MessageRole::User, $messages[2]->role);
    }

    public function test_messages_returns_empty_when_no_chat_is_set(): void
    {
        $messages = (new WhatsAppAgent)->messages();

        $this->assertSame([], $messages);
    }

    public function test_continue_last_conversation_resolves_chat_for_sender(): void
    {
        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'sender_jid' => '222@s.whatsapp.net', 'ts' => 1700000000, 'from_me' => 0, 'text' => 'older']);
        $this->seedMessage(['rowid' => 2, 'chat_jid' => '999@g.us', 'sender_jid' => '222@s.whatsapp.net', 'ts' => 1700000050, 'from_me' => 0, 'text' => 'newer']);
        $this->seedMessage(['rowid' => 3, 'chat_jid' => '888@g.us', 'sender_jid' => 'someone-else@s.whatsapp.net', 'ts' => 1700000100, 'from_me' => 0, 'text' => 'unrelated']);

        $agent = (new WhatsAppAgent)->continueLastConversation('222@s.whatsapp.net');

        $this->assertSame('999@g.us', $agent->currentConversation());
        $this->assertSame('222@s.whatsapp.net', $agent->conversationParticipant());
    }

    public function test_continue_last_conversation_leaves_chat_null_when_sender_has_no_history(): void
    {
        $agent = (new WhatsAppAgent)->continueLastConversation('nobody@s.whatsapp.net');

        $this->assertNull($agent->currentConversation());
        $this->assertSame('nobody@s.whatsapp.net', $agent->conversationParticipant());
    }

    public function test_messages_returns_an_array_of_message_objects(): void
    {
        $this->seedMessage(['rowid' => 1, 'ts' => 1700000000, 'from_me' => 0, 'text' => 'hi']);

        $agent = (new WhatsAppAgent)->forChat('111@s.whatsapp.net', '111@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertIsArray($messages);
        $this->assertContainsOnlyInstancesOf(Message::class, $messages);
    }

    public function test_messages_in_group_chat_prefix_inbound_with_sender_label(): void
    {
        $this->seedMessage([
            'rowid' => 1, 'ts' => 1700000000, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => 'alice@s.whatsapp.net', 'sender_name' => 'Alice',
            'text' => 'first',
        ]);
        $this->seedMessage([
            'rowid' => 2, 'ts' => 1700000010, 'from_me' => 1,
            'chat_jid' => '999@g.us', 'sender_jid' => 'bot@s.whatsapp.net', 'sender_name' => 'Bot',
            'text' => 'agent reply',
        ]);
        $this->seedMessage([
            'rowid' => 3, 'ts' => 1700000020, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => 'bob@s.whatsapp.net', 'sender_name' => 'Bob',
            'text' => 'second',
        ]);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        // Preamble in groups is [context note, ack], so historical rows start at index 2.
        $this->assertSame('[Alice] first', $messages[2]->content);
        $this->assertSame(MessageRole::User, $messages[2]->role);
        $this->assertSame('agent reply', $messages[3]->content);
        $this->assertSame(MessageRole::Assistant, $messages[3]->role);
        $this->assertSame('[Bob] second', $messages[4]->content);
        $this->assertSame(MessageRole::User, $messages[4]->role);
    }

    public function test_messages_in_dm_are_not_prefixed_and_have_no_context_note(): void
    {
        $this->seedMessage(['rowid' => 1, 'ts' => 1700000000, 'from_me' => 0, 'text' => 'hi']);

        $agent = (new WhatsAppAgent)->forChat('111@s.whatsapp.net', '111@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertCount(1, $messages);
        $this->assertSame('hi', $messages[0]->content);
    }

    public function test_messages_fall_back_to_phone_from_jid_when_sender_name_is_null(): void
    {
        $this->seedMessage([
            'rowid' => 1, 'ts' => 1700000000, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => '12345@s.whatsapp.net', 'sender_name' => null,
            'text' => 'hello',
        ]);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', '12345@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertSame('[12345] hello', $messages[2]->content);
    }

    public function test_sender_label_format_is_overridable_via_formatter(): void
    {
        $this->seedMessage([
            'rowid' => 1, 'ts' => 1700000000, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => 'alice@s.whatsapp.net', 'sender_name' => 'Alice',
            'text' => 'hi',
        ]);

        $formatter = new class extends GroupParticipantFormatter
        {
            public function senderLabel(?string $chatJid, ?string $senderName, ?string $senderJid): ?string
            {
                if (! $this->isGroupChat($chatJid) || $senderName === null) {
                    return null;
                }

                return "{$senderName}:";
            }
        };

        $this->app->instance(GroupParticipantFormatter::class, $formatter);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertSame('Alice: hi', $messages[2]->content);
    }

    public function test_messages_prepend_group_context_preamble_in_groups(): void
    {
        $this->seedMessage([
            'rowid' => 1, 'ts' => 1700000000, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => 'alice@s.whatsapp.net', 'sender_name' => 'Alice',
            'text' => 'hello',
        ]);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertSame(MessageRole::User, $messages[0]->role);
        $this->assertStringContainsString('group chat', $messages[0]->content);
        $this->assertStringContainsString('square brackets', $messages[0]->content);

        $this->assertSame(MessageRole::Assistant, $messages[1]->role);
        $this->assertSame('Understood.', $messages[1]->content);
    }

    public function test_messages_prepend_preamble_even_with_empty_history_in_groups(): void
    {
        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertCount(2, $messages);
        $this->assertStringContainsString('group chat', $messages[0]->content);
        $this->assertSame('Understood.', $messages[1]->content);
    }

    public function test_group_context_note_is_overridable_via_formatter(): void
    {
        $formatter = new class extends GroupParticipantFormatter
        {
            public function contextNote(?string $chatJid): ?string
            {
                return $this->isGroupChat($chatJid) ? 'CUSTOM NOTE' : null;
            }
        };

        $this->app->instance(GroupParticipantFormatter::class, $formatter);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertSame('CUSTOM NOTE', $messages[0]->content);
        // The default ack still fires when only the note is overridden.
        $this->assertSame('Understood.', $messages[1]->content);
    }

    public function test_group_context_note_can_be_suppressed_via_formatter(): void
    {
        $this->seedMessage([
            'rowid' => 1, 'ts' => 1700000000, 'from_me' => 0,
            'chat_jid' => '999@g.us', 'sender_jid' => 'alice@s.whatsapp.net', 'sender_name' => 'Alice',
            'text' => 'hi',
        ]);

        $formatter = new class extends GroupParticipantFormatter
        {
            public function contextNote(?string $chatJid): ?string
            {
                return null;
            }
        };

        $this->app->instance(GroupParticipantFormatter::class, $formatter);

        $agent = (new WhatsAppAgent)->forChat('999@g.us', 'alice@s.whatsapp.net');

        $messages = $agent->messages();

        $this->assertCount(1, $messages);
        $this->assertSame('[Alice] hi', $messages[0]->content);
    }

    private function seedMessage(array $attrs): void
    {
        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert(array_merge([
            'chat_jid' => '111@s.whatsapp.net',
            'chat_name' => 'Alice',
            'msg_id' => 'M-'.($attrs['rowid'] ?? 0),
            'sender_jid' => '111@s.whatsapp.net',
            'sender_name' => 'Alice',
            'display_text' => null,
            'media_type' => null,
        ], $attrs));
    }
}
