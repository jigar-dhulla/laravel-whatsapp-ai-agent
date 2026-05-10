<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class ChatsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    public function test_it_lists_only_dms_by_default(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    ['jid' => '111@s.whatsapp.net', 'kind' => 'dm', 'name' => 'Alice', 'last_message_ts' => '2026-05-10T19:34:08Z'],
                    ['jid' => '222@g.us', 'kind' => 'group', 'name' => 'Team', 'last_message_ts' => '2026-05-10T18:00:00Z'],
                ],
                'error' => null,
            ])),
        ]);

        $this->artisan('wa:chats')
            ->assertExitCode(0)
            ->expectsOutputToContain('111@s.whatsapp.net')
            ->doesntExpectOutputToContain('222@g.us')
            ->expectsOutputToContain('1 chat(s).');
    }

    public function test_it_lists_all_chats_with_all_flag(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    ['jid' => '111@s.whatsapp.net', 'kind' => 'dm', 'name' => 'Alice', 'last_message_ts' => '2026-05-10T19:34:08Z'],
                    ['jid' => '222@g.us', 'kind' => 'group', 'name' => 'Team', 'last_message_ts' => '2026-05-10T18:00:00Z'],
                ],
                'error' => null,
            ])),
        ]);

        $this->artisan('wa:chats', ['--all' => true])
            ->assertExitCode(0)
            ->expectsOutputToContain('111@s.whatsapp.net')
            ->expectsOutputToContain('222@g.us')
            ->expectsOutputToContain('2 chat(s).');
    }

    public function test_it_warns_when_no_chats_returned(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode(['success' => true, 'data' => [], 'error' => null])),
        ]);

        $this->artisan('wa:chats')
            ->assertExitCode(0)
            ->expectsOutputToContain('No chats found');
    }
}