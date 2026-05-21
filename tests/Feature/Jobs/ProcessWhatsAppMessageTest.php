<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs;

use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs\Fixtures\RecordingWhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs\Fixtures\SimplePromptableAgent;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;
use Mockery\MockInterface;

class ProcessWhatsAppMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();

        $this->setUpWacliDatabase();
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

    public function test_it_forwards_reply_to_msg_id_to_wacli_send(): void
    {
        WhatsAppAgent::fake(['Got it!']);

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
            replyToMsgId: 'MSG-42',
        );

        $job->handle(new Wacli);

        Process::assertRan(function ($process) {
            $command = is_array($process->command) ? $process->command : explode(' ', $process->command);

            $index = array_search('--reply-to', $command, true);

            return $index !== false && ($command[$index + 1] ?? null) === 'MSG-42';
        });
    }

    public function test_it_skips_wacli_send_when_agent_returns_empty_reply(): void
    {
        WhatsAppAgent::fake(['']);

        /** @var Wacli|MockInterface $wacli */
        $wacli = $this->mock(Wacli::class);

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

    public function test_it_handles_promptable_agents_without_remembers_trait(): void
    {
        SimplePromptableAgent::fake(['Hi!']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: 'Alice',
            senderJid: '111@s.whatsapp.net',
            senderName: 'Alice',
            body: 'hey simple agent',
            agentClass: SimplePromptableAgent::class,
        );

        $job->handle(new Wacli);

        SimplePromptableAgent::assertPrompted('hey simple agent');

        Process::assertRan(function ($process) {
            $command = is_array($process->command)
                ? implode(' ', $process->command)
                : $process->command;

            return str_contains($command, 'send') && str_contains($command, 'Hi!');
        });
    }

    public function test_it_calls_for_chat_on_agents_using_remembers_trait(): void
    {
        RecordingWhatsAppAgent::reset();
        RecordingWhatsAppAgent::fake(['Hello there!']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '111@s.whatsapp.net',
            chatName: 'Alice',
            senderJid: '222@s.whatsapp.net',
            senderName: 'Bob',
            body: 'hey agent',
            agentClass: RecordingWhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        $this->assertSame(
            [['111@s.whatsapp.net', '222@s.whatsapp.net']],
            RecordingWhatsAppAgent::$forChatCalls,
        );
    }

    public function test_it_prefixes_body_with_sender_label_in_group(): void
    {
        WhatsAppAgent::fake(['ok']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '999@g.us',
            chatName: 'Squad',
            senderJid: 'bob@s.whatsapp.net',
            senderName: 'Bob',
            body: 'hey agent',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('[Bob] hey agent');
    }

    public function test_it_does_not_prefix_body_in_dm(): void
    {
        WhatsAppAgent::fake(['ok']);

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
        WhatsAppAgent::assertNotPrompted(fn ($prompt) => str_contains($prompt->prompt, '['));
    }

    public function test_it_uses_phone_fallback_when_sender_name_is_null_in_group(): void
    {
        WhatsAppAgent::fake(['ok']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '999@g.us',
            chatName: 'Squad',
            senderJid: '12345@s.whatsapp.net',
            senderName: null,
            body: 'hey agent',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('[12345] hey agent');
    }

    public function test_it_uses_unknown_when_sender_jid_and_name_are_null_in_group(): void
    {
        WhatsAppAgent::fake(['ok']);

        Process::fake([
            '*doctor*' => Process::result(output: json_encode(['success' => true, 'data' => ['lock_held' => false]]), exitCode: 0),
            '*send*' => Process::result(exitCode: 0),
        ]);

        $job = new ProcessWhatsAppMessage(
            chatJid: '999@g.us',
            chatName: 'Squad',
            senderJid: null,
            senderName: null,
            body: 'hey agent',
            agentClass: WhatsAppAgent::class,
        );

        $job->handle(new Wacli);

        WhatsAppAgent::assertPrompted('[Unknown] hey agent');
    }
}
