<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class WhatsAppMessageReaderTest extends TestCase
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

    public function test_fetch_new_returns_messages_above_the_last_processed_rowid(): void
    {
        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'old', 'from_me' => 0]);
        $this->seedMessage(['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'new', 'from_me' => 0]);

        Cache::put(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 1);

        $router = AgentRouter::fromConfig();
        $messages = (new WhatsAppMessageReader)->fetchNew($router);

        $this->assertCount(1, $messages);
        $this->assertSame('new', $messages->first()->text);
    }

    public function test_fetch_new_filters_out_outbound_and_unscoped_messages(): void
    {
        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'inbound', 'from_me' => 0]);
        $this->seedMessage(['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'mine', 'from_me' => 1]);
        $this->seedMessage(['rowid' => 3, 'chat_jid' => '999@s.whatsapp.net', 'text' => 'other dm', 'from_me' => 0]);

        Cache::put(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 0);

        $router = AgentRouter::fromConfig();
        $messages = (new WhatsAppMessageReader)->fetchNew($router);

        $this->assertCount(1, $messages);
        $this->assertSame('inbound', $messages->first()->text);
    }

    public function test_fetch_new_returns_empty_when_no_chats_or_groups_configured(): void
    {
        config()->set('whatsapp-agent.agents', []);

        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'hi', 'from_me' => 0]);

        $router = AgentRouter::fromConfig();
        $this->assertCount(0, (new WhatsAppMessageReader)->fetchNew($router));
    }

    public function test_bookmark_current_head_sets_last_rowid_to_max(): void
    {
        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'a', 'from_me' => 0]);
        $this->seedMessage(['rowid' => 7, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'b', 'from_me' => 0]);

        $head = (new WhatsAppMessageReader)->bookmarkCurrentHead();

        $this->assertSame(7, $head);
        $this->assertSame(7, (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID));
    }

    public function test_mark_processed_persists_the_rowid(): void
    {
        (new WhatsAppMessageReader)->markProcessed(42);

        $this->assertSame(42, (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID));
    }

    private function seedMessage(array $attrs): void
    {
        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert(array_merge([
            'msg_id' => 'M-'.($attrs['rowid'] ?? 0),
            'sender_jid' => $attrs['chat_jid'] ?? '111@s.whatsapp.net',
            'sender_name' => 'Sender',
            'chat_name' => 'Chat',
            'ts' => 1700000000,
            'display_text' => null,
            'media_type' => null,
        ], $attrs));
    }
}
