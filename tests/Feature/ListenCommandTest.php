<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class ListenCommandTest extends TestCase
{
    public function test_it_fails_early_when_no_agents_are_configured(): void
    {
        config()->set('whatsapp-agent.agents', []);

        $this->artisan('wa:listen')
            ->assertExitCode(1)
            ->expectsOutputToContain('vendor:publish');
    }

    public function test_it_exits_successfully_with_warning_when_all_agents_have_empty_scope(): void
    {
        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ]]);

        $this->artisan('wa:listen')
            ->assertExitCode(0)
            ->expectsOutputToContain('whatsapp-agent.php');
    }

    public function test_it_processes_a_matching_message_and_dispatches_job(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000000, 'from_me' => 0, 'text' => 'old', 'display_text' => 'old'],
            ['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M2', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000001, 'from_me' => 0, 'text' => 'hey agent', 'display_text' => 'hey agent'],
        ]);

        Process::preventStrayProcesses();
        Process::fake([
            '*sync*' => Process::result(output: json_encode(['success' => true, 'data' => ['messages_stored' => 0]])),
        ]);

        Bus::fake();

        $this->partialMock(WhatsAppMessageReader::class, function ($mock) {
            $mock->shouldReceive('bookmarkCurrentHead')->once()->andReturn(0);
        });

        $this->artisan('wa:listen', ['--once' => true])
            ->assertExitCode(0);

        Bus::assertDispatched(ProcessWhatsAppMessage::class, function ($job) {
            return $job->body === 'hey agent' && $job->agentClass === WhatsAppAgent::class;
        });
    }

    public function test_it_dispatches_one_job_per_matching_agent(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [
            [
                'agent' => WhatsAppAgent::class,
                'triggers' => ['agent'],
                'chats' => ['111@s.whatsapp.net'],
                'groups' => [],
            ],
            [
                'agent' => WhatsAppAgent::class,
                'triggers' => ['agent'],
                'chats' => ['111@s.whatsapp.net'],
                'groups' => [],
            ],
        ]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000000, 'from_me' => 0, 'text' => 'hey agent', 'display_text' => 'hey agent'],
        ]);

        Process::preventStrayProcesses();
        Process::fake([
            '*sync*' => Process::result(output: json_encode(['success' => true, 'data' => ['messages_stored' => 0]])),
        ]);

        Bus::fake();

        $this->partialMock(WhatsAppMessageReader::class, function ($mock) {
            $mock->shouldReceive('bookmarkCurrentHead')->once()->andReturn(0);
        });

        $this->artisan('wa:listen', ['--once' => true])
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(ProcessWhatsAppMessage::class, 2);
    }

    public function test_it_advances_bookmark_irrespective_of_trigger_match(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            // Matches agent and trigger
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000000, 'from_me' => 0, 'text' => 'hey agent', 'display_text' => 'hey agent'],
            // Matches agent BUT NOT trigger
            ['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M2', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000001, 'from_me' => 0, 'text' => 'no match', 'display_text' => 'no match'],
            // Does NOT match agent (unallowed JID)
            ['rowid' => 3, 'chat_jid' => '999@s.whatsapp.net', 'chat_name' => 'Bob', 'msg_id' => 'M3', 'sender_jid' => '999@s.whatsapp.net', 'sender_name' => 'Bob', 'ts' => 1700000002, 'from_me' => 0, 'text' => 'hey agent', 'display_text' => 'hey agent'],
            // Outbound message
            ['rowid' => 4, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M4', 'sender_jid' => 'me', 'sender_name' => 'Me', 'ts' => 1700000003, 'from_me' => 1, 'text' => 'reply', 'display_text' => 'reply'],
        ]);

        Process::preventStrayProcesses();
        Process::fake(['*sync*' => Process::result(output: json_encode(['success' => true, 'data' => ['messages_stored' => 0]]))]);
        Bus::fake();

        $this->partialMock(WhatsAppMessageReader::class, function ($mock) {
            $mock->shouldReceive('bookmarkCurrentHead')->once()->andReturn(0);
        });

        $this->artisan('wa:listen', ['--once' => true])
            ->assertExitCode(0);

        Bus::assertDispatchedTimes(ProcessWhatsAppMessage::class, 1);
        $this->assertSame(4, (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID));
    }

    private function seedSchema(): void
    {
        $this->setUpWacliDatabase();
    }
}
