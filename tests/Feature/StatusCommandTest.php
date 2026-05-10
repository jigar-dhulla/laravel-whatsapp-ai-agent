<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class StatusCommandTest extends TestCase
{
    public function test_it_renders_doctor_information_and_agent_summary(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    'store_dir' => '/tmp/.wacli',
                    'authenticated' => true,
                    'connected' => true,
                    'lock_held' => false,
                    'connection_state' => 'connected',
                    'linked_jid' => '911234567890@s.whatsapp.net',
                    'fts_enabled' => true,
                    'store' => [
                        'messages' => 100,
                        'chats' => 50,
                    ],
                ],
                'error' => null,
            ])),
        ]);

        config()->set('whatsapp-agent.agents', [
            [
                'agent' => WhatsAppAgent::class,
                'triggers' => ['@agent'],
                'chats' => ['111@s.whatsapp.net'],
                'groups' => [],
            ],
        ]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('/tmp/.wacli')
            ->expectsOutputToContain('911234567890@s.whatsapp.net')
            ->expectsOutputToContain('connected')
            ->expectsOutputToContain('Messages: 100, Chats: 50')
            ->expectsOutputToContain('WhatsAppAgent')
            ->expectsOutputToContain('@agent');
    }

    public function test_it_warns_when_doctor_fails(): void
    {
        Process::preventStrayProcesses();
        // Return success: false to trigger error in doctor()
        Process::fake([
            '*' => Process::result(output: json_encode(['success' => false])),
        ]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('Could not run `wacli doctor`');
    }

    public function test_it_shows_inactive_when_agent_has_no_scope(): void
    {
        Process::preventStrayProcesses();
        Process::fake(['*' => Process::result(output: json_encode(['success' => false]))]);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ]]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('inactive');
    }
}
