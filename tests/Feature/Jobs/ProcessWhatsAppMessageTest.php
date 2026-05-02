<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Process;
use LaravelWhatsApp\Agents\GenericAgent;
use LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use LaravelWhatsApp\Services\Wacli;
use LaravelWhatsApp\Tests\TestCase;

class ProcessWhatsAppMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    public function test_it_calls_agent_and_sends_reply_via_wacli(): void
    {
        GenericAgent::fake(['Hello there!']);

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
            agentClass: GenericAgent::class,
        );

        $job->handle(new Wacli);

        GenericAgent::assertPrompted('hey agent');

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : $process->command;

            return str_contains($command, 'send') && str_contains($command, 'Hello there!');
        });
    }

    public function test_it_skips_wacli_send_when_agent_returns_empty_reply(): void
    {
        GenericAgent::fake(['']);

        /** @var Wacli|\Mockery\MockInterface $wacli */
        $wacli = $this->mock(Wacli::class);
        $wacli->shouldReceive('waitUntilUnlocked')->once();

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: null,
            senderJid: null,
            senderName: null,
            body: 'hey agent',
            agentClass: GenericAgent::class,
        );

        $job->handle($wacli);

        GenericAgent::assertPrompted('hey agent');

        $wacli->shouldNotHaveReceived('send');
    }

    public function test_it_passes_provider_override_to_prompt(): void
    {
        GenericAgent::fake(['Overridden provider reply']);

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
            agentClass: GenericAgent::class,
            providerOverride: 'anthropic',
            modelOverride: 'claude-opus-4-7',
        );

        $job->handle(new Wacli);

        GenericAgent::assertPrompted('test');
    }

    public function test_it_checks_wacli_lock_status_before_processing(): void
    {
        GenericAgent::fake(['Reply']);

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
            agentClass: GenericAgent::class,
        );

        $job->handle(new Wacli);

        GenericAgent::assertPrompted('test');
        Process::assertRan(fn ($process) => str_contains(implode(' ', $process->command), 'doctor'));
    }

    public function test_it_waits_for_lock_to_be_released(): void
    {
        GenericAgent::fake(['Reply']);

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
            agentClass: GenericAgent::class,
        );

        $job->handle(new Wacli);

        GenericAgent::assertPrompted('test');
        // Should have called doctor at least twice (once for lock check, once when released)
        $this->assertGreaterThanOrEqual(2, $callCount);
    }
}
