<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
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
            'mention_sender' => true,
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
            return $job->body === 'hey agent'
                && $job->agentClass === WhatsAppAgent::class
                && $job->ts->getTimestamp() === 1700000001
                && $job->mentionSender === true;
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

    public function test_it_dispatches_job_when_message_quotes_the_agent_without_a_trigger(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['@agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'BOT1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Agent', 'ts' => 1700000000, 'from_me' => 1, 'text' => 'I am the agent', 'display_text' => 'I am the agent', 'quoted_msg_id' => null],
            ['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M2', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000001, 'from_me' => 0, 'text' => 'thanks!', 'display_text' => 'thanks!', 'quoted_msg_id' => 'BOT1'],
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
            return $job->body === 'thanks!' && $job->agentClass === WhatsAppAgent::class;
        });
    }

    public function test_it_does_not_dispatch_when_reply_quotes_another_user_without_a_trigger(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['@agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'U1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000000, 'from_me' => 0, 'text' => 'first', 'display_text' => 'first', 'quoted_msg_id' => null],
            ['rowid' => 2, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'U2', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000001, 'from_me' => 0, 'text' => 'reply to first', 'display_text' => 'reply to first', 'quoted_msg_id' => 'U1'],
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

        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
    }

    public function test_it_defers_placeholder_rows_to_resolve_job_and_advances_bookmark(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert([
            ['rowid' => 1, 'chat_jid' => '111@s.whatsapp.net', 'chat_name' => 'Alice', 'msg_id' => 'M1', 'sender_jid' => '111@s.whatsapp.net', 'sender_name' => 'Alice', 'ts' => 1700000000, 'from_me' => 0, 'text' => null, 'display_text' => '(message)'],
        ]);

        Process::preventStrayProcesses();
        Process::fake([
            '*sync*' => Process::result(output: json_encode(['success' => true, 'data' => ['messages_stored' => 0]])),
        ]);

        Bus::fake();

        $this->partialMock(WhatsAppMessageReader::class, function ($mock) {
            $mock->shouldReceive('bookmarkCurrentHead')->once()->andReturn(0);
            $mock->shouldReceive('markProcessed')->once()->with(1);
        });

        $this->artisan('wa:listen', ['--once' => true])
            ->assertExitCode(0);

        Bus::assertDispatched(ResolveWhatsAppMessage::class, function (ResolveWhatsAppMessage $job) {
            return $job->rowid === 1;
        });
        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
    }

    public function test_it_does_not_dispatch_resolve_job_for_real_messages(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

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

        Bus::assertNotDispatched(ResolveWhatsAppMessage::class);
        Bus::assertDispatched(ProcessWhatsAppMessage::class);
    }

    private function seedSchema(): void
    {
        $this->setUpWacliDatabase();
    }
}
