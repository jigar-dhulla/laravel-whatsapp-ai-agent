<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Listening;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Listening\MessagePipeline;
use JigarDhulla\LaravelWhatsApp\Listening\NullListenerReporter;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class MessagePipelineTest extends TestCase
{
    public function test_it_dispatches_a_job_per_matching_agent_and_advances_bookmark(): void
    {
        $this->setUpWacliDatabase();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 5, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M5', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000001, 'from_me' => 0, 'text' => 'hey agent', 'display_text' => 'hey agent'],
        ]);

        Bus::fake();

        $this->process();

        Bus::assertDispatched(ProcessWhatsAppMessage::class, function ($job) {
            return $job->body === 'hey agent' && $job->agentClass === WhatsAppAgent::class;
        });

        $this->assertSame(5, (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID));
    }

    public function test_it_defers_placeholder_rows_and_marks_them_processed(): void
    {
        $this->setUpWacliDatabase();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 7, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M7', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000002, 'from_me' => 0, 'text' => null, 'display_text' => '(message)'],
        ]);

        Bus::fake();

        $this->process();

        Bus::assertDispatched(ResolveWhatsAppMessage::class, fn (ResolveWhatsAppMessage $job) => $job->rowid === 7);
        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
        $this->assertSame(7, (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID));
    }

    private function process(): void
    {
        app(MessagePipeline::class)->process(
            app(WhatsAppMessageReader::class),
            AgentRouter::fromConfig(),
            new NullListenerReporter,
        );
    }
}
