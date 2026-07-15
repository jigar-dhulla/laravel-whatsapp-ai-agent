<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs;

use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;
use Mockery;

class ResolveWhatsAppMessageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpWacliDatabase();
    }

    public function test_it_releases_itself_when_body_is_still_a_placeholder(): void
    {
        $this->seedMessage(['rowid' => 1, 'text' => null, 'display_text' => '(message)']);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        Bus::fake();

        $queueJob = Mockery::mock(JobContract::class);
        $queueJob->shouldReceive('release')->once()->with(2);
        $queueJob->shouldIgnoreMissing();

        $job = new ResolveWhatsAppMessage(1);
        $job->setJob($queueJob);

        $job->handle();

        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
    }

    public function test_it_dispatches_process_job_when_body_has_been_filled(): void
    {
        $this->seedMessage(['rowid' => 1, 'text' => 'hey agent', 'display_text' => '(message)']);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        Bus::fake();

        (new ResolveWhatsAppMessage(1))->handle();

        Bus::assertDispatched(ProcessWhatsAppMessage::class, function (ProcessWhatsAppMessage $job) {
            return $job->body === 'hey agent'
                && $job->agentClass === WhatsAppAgent::class
                && $job->chatJid === '111@s.whatsapp.net'
                && $job->ts->getTimestamp() === 1700000000
                && $job->mentionSender === false;
        });
    }

    public function test_it_forwards_mention_sender_agent_config_to_process_job(): void
    {
        $this->seedMessage(['rowid' => 1, 'text' => 'hey agent', 'display_text' => 'hey agent']);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
            'mention_sender' => true,
        ]]);

        Bus::fake();

        (new ResolveWhatsAppMessage(1))->handle();

        Bus::assertDispatched(ProcessWhatsAppMessage::class, fn (ProcessWhatsAppMessage $job) => $job->mentionSender === true);
    }

    public function test_it_dispatches_one_process_job_per_matching_agent(): void
    {
        $this->seedMessage(['rowid' => 1, 'text' => 'hey agent', 'display_text' => 'hey agent']);

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

        Bus::fake();

        (new ResolveWhatsAppMessage(1))->handle();

        Bus::assertDispatchedTimes(ProcessWhatsAppMessage::class, 2);
    }

    public function test_it_dispatches_nothing_when_no_agent_triggers_match(): void
    {
        $this->seedMessage(['rowid' => 1, 'text' => 'totally unrelated', 'display_text' => 'totally unrelated']);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        Bus::fake();

        (new ResolveWhatsAppMessage(1))->handle();

        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
    }

    public function test_it_dispatches_when_reply_quotes_own_message_without_trigger(): void
    {
        $this->seedMessage(['rowid' => 1, 'msg_id' => 'BOT1', 'from_me' => 1, 'text' => 'I posted your ride (Ride #29)', 'display_text' => 'I posted your ride (Ride #29)']);
        $this->seedMessage(['rowid' => 2, 'text' => "What's the status on this?", 'display_text' => "What's the status on this?", 'quoted_msg_id' => 'BOT1']);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        Bus::fake();

        (new ResolveWhatsAppMessage(2))->handle();

        Bus::assertDispatched(ProcessWhatsAppMessage::class, function (ProcessWhatsAppMessage $job) {
            return $job->body === "What's the status on this?"
                && $job->agentClass === WhatsAppAgent::class;
        });
    }

    public function test_it_returns_silently_when_row_no_longer_exists(): void
    {
        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => ['agent'],
            'chats' => ['111@s.whatsapp.net'],
            'groups' => [],
        ]]);

        Bus::fake();

        (new ResolveWhatsAppMessage(999))->handle();

        Bus::assertNotDispatched(ProcessWhatsAppMessage::class);
    }

    private function seedMessage(array $attrs): void
    {
        DB::connection(WhatsAppMessageReader::CONNECTION_NAME)->table('messages')->insert(array_merge([
            'chat_jid' => '111@s.whatsapp.net',
            'chat_name' => 'Alice',
            'msg_id' => 'M-'.($attrs['rowid'] ?? 0),
            'sender_jid' => '111@s.whatsapp.net',
            'sender_name' => 'Alice',
            'ts' => 1700000000,
            'from_me' => 0,
            'text' => null,
            'display_text' => null,
            'media_type' => null,
        ], $attrs));
    }
}
