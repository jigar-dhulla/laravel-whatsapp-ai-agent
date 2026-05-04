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
            '*doctor*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    'store_dir' => '/tmp/.wacli',
                    'authenticated' => true,
                    'connected' => false,
                    'lock_held' => false,
                ],
                'error' => null,
            ])),
        ]);

        config()->set('whatsapp-agent.agents', [
            [
                'agent' => WhatsAppAgent::class,
                'provider' => 'anthropic',
                'model' => 'claude-opus-4-7',
                'triggers' => ['@agent'],
                'chats' => ['111@s.whatsapp.net'],
                'groups' => [],
            ],
        ]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('/tmp/.wacli')
            ->expectsOutputToContain('WhatsAppAgent')
            ->expectsOutputToContain('anthropic / claude-opus-4-7')
            ->expectsOutputToContain('@agent');
    }

    public function test_it_warns_when_doctor_fails(): void
    {
        Process::preventStrayProcesses();
        Process::fake([
            '*doctor*' => Process::result(exitCode: 1),
        ]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('Could not run `wacli doctor`');
    }

    public function test_it_shows_inactive_when_agent_has_no_scope(): void
    {
        Process::preventStrayProcesses();
        Process::fake(['*doctor*' => Process::result(exitCode: 1)]);

        config()->set('whatsapp-agent.agents', [[
            'agent' => WhatsAppAgent::class,
            'provider' => null,
            'model' => null,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ]]);

        $this->artisan('wa:status')
            ->assertExitCode(0)
            ->expectsOutputToContain('inactive');
    }
}
