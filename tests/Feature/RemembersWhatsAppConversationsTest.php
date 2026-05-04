<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use LaravelWhatsApp\Agents\WhatsAppAgent;
use LaravelWhatsApp\Services\WhatsAppMessageReader;
use LaravelWhatsApp\Tests\TestCase;

class RemembersWhatsAppConversationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.'.WhatsAppMessageReader::CONNECTION_NAME, [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => false,
        ]);

        Schema::connection(WhatsAppMessageReader::CONNECTION_NAME)->create('messages', function (Blueprint $table) {
            $table->bigIncrements('rowid');
            $table->string('chat_jid');
            $table->string('chat_name')->nullable();
            $table->string('msg_id');
            $table->string('sender_jid')->nullable();
            $table->string('sender_name')->nullable();
            $table->bigInteger('ts');
            $table->boolean('from_me');
            $table->text('text')->nullable();
            $table->text('display_text')->nullable();
            $table->string('media_type')->nullable();
        });
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
