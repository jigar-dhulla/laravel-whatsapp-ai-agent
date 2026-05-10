<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class WhatsAppMessageReaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWacliDatabase();
    }

    public function test_fetch_new_returns_messages_above_the_last_processed_rowid(): void
    {
        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'old', 'from_me' => 0]);
        $this->seedMessage(['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'new', 'from_me' => 0]);

        Cache::put(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 1);

        $messages = (new WhatsAppMessageReader)->fetchNew();

        $this->assertCount(1, $messages);
        $this->assertSame('new', $messages->first()->text);
    }

    public function test_fetch_new_does_not_filter_anything(): void
    {
        $this->seedMessage(['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'inbound', 'from_me' => 0]);
        $this->seedMessage(['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'text' => 'mine', 'from_me' => 1]);
        $this->seedMessage(['rowid' => 3, 'chat_jid' => '999@s.whatsapp.net', 'text' => 'other dm', 'from_me' => 0]);

        Cache::put(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 0);

        $messages = (new WhatsAppMessageReader)->fetchNew();

        $this->assertCount(3, $messages);
    }

    public function test_fetch_new_respects_limit(): void
    {
        $this->seedMessage(['rowid' => 1]);
        $this->seedMessage(['rowid' => 2]);
        $this->seedMessage(['rowid' => 3]);

        $messages = (new WhatsAppMessageReader)->fetchNew(limit: 2);

        $this->assertCount(2, $messages);
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
            'chat_jid' => '111@s.whatsapp.net',
            'sender_jid' => '111@s.whatsapp.net',
            'sender_name' => 'Sender',
            'chat_name' => 'Chat',
            'ts' => 1700000000,
            'from_me' => 0,
            'display_text' => null,
            'media_type' => null,
        ], $attrs));
    }
}
