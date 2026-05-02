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

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: null,
            senderJid: null,
            senderName: null,
            body: 'hey agent',
            agentClass: GenericAgent::class,
        );

        $job->handle(new Wacli);

        GenericAgent::assertPrompted('hey agent');

        Process::assertNothingRan();
    }

    public function test_it_passes_provider_override_to_prompt(): void
    {
        GenericAgent::fake(['Overridden provider reply']);

        Process::fake([
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
}
