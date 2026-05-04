<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;
use Mockery\MockInterface;

class ProcessWhatsAppMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();

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

    public function test_it_calls_agent_and_sends_reply_via_wacli(): void
    {
        WhatsAppAgent::fake(['Hello there!']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: 'Alice',
            senderJid: '111@s.whatsapp.net',
            senderName: 'Alice',
            body: 'hey agent',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('hey agent');

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : $process->command;

            return str_contains($command, 'send') && str_contains($command, 'Hello there!');
        });
    }

    public function test_it_skips_wacli_send_when_agent_returns_empty_reply(): void
    {
        WhatsAppAgent::fake(['']);

        /** @var Wacli|MockInterface $wacli */
        $wacli = $this->mock(Wacli::class);
        $wacli->shouldReceive('waitUntilUnlocked')->once();

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: null,
            senderJid: null,
            senderName: null,
            body: 'hey agent',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle($wacli);

        WhatsAppAgent::assertPrompted('hey agent');

        $wacli->shouldNotHaveReceived('send');
    }

    public function test_it_passes_provider_override_to_prompt(): void
    {
        WhatsAppAgent::fake(['Overridden provider reply']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: null,
            senderJid: null,
            senderName: null,
            body: 'test',
            agentClass: WhatsAppAgent::class,
            providerOverride: 'anthropic',
            modelOverride: 'claude-opus-4-7',
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('test');
    }

    public function test_it_checks_wacli_lock_status_before_processing(): void
    {
        WhatsAppAgent::fake(['Reply']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: 'Test',
            senderJid: '222@s.whatsapp.net',
            senderName: 'User',
            body: 'test',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('test');
        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'doctor'));
    }

    public function test_it_waits_for_lock_to_be_released(): void
    {
        WhatsAppAgent::fake(['Reply']);

        $callCount = 0;
        Process::fake(function ($process) use (&$callCount) {
            $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

            if (str_contains($command, 'doctor')) {
                $callCount++;
                // First call: lock held, second call: lock released
                $lockHeld = $callCount <= 1;

                return Process::result(
                    output: json_encode(['success' => true, 'data' => ['lock_held' => $lockHeld]]),
                    exitCode: 0
                );
            } elseif (str_contains($command, 'send')) {
                return Process::result(exitCode: 0);
            }

            return Process::result(exitCode: 0);
        });

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: 'Test',
            senderJid: '222@s.whatsapp.net',
            senderName: 'User',
            body: 'test',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('test');
        // Should have called doctor at least twice (once for lock check, once when released)
        $this->assertGreaterThanOrEqual(2, $callCount);
    }
}
