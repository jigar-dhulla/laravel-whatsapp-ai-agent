<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use LaravelWhatsApp\Agents\WhatsAppAgent;
use LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use LaravelWhatsApp\Services\WhatsAppMessageReader;
use LaravelWhatsApp\Tests\TestCase;

class ListenCommandTest extends TestCase
{
    public function test_it_fails_early_when_no_agents_are_configured(): void
    {
        config()->set('whatsapp-agent.agents', []);

        $this->artisan('wa:listen')
            ->assertExitCode(1)
            ->expectsOutputToContain('vendor:publish');
    }

    public function test_it_fails_early_when_all_agents_have_empty_scope(): void
    {
        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'provider' => 'anthropic',
            'model' => 'claude-opus-4-7',
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ]]);

        $this->artisan('wa:listen')
            ->assertExitCode(1)
            ->expectsOutputToContain('whatsapp-agent.php');
    }

    public function test_it_processes_a_matching_message_and_dispatches_job(): void
    {
        $this->seedSchema();

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'provider' => 'anthropic',
            'model' => 'claude-opus-4-7',
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
                'provider' => 'anthropic',
                'model' => 'claude-opus-4-7',
                'triggers' => ['agent'],
                'chats' => ['111@s.whatsapp.net'],
                'groups' => [],
            ],
            [
                'agent' => WhatsAppAgent::class,
                'provider' => 'openai',
                'model' => 'gpt-4o',
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

    private function seedSchema(): void
    {
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
}
