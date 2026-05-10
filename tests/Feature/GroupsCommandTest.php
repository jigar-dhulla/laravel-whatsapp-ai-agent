<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class GroupsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    public function test_it_lists_groups_in_a_table(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    [
                        'JID' => '999@g.us',
                        'Name' => 'Test Group',
                        'OwnerJID' => '888@lid',
                        'UpdatedAt' => '2026-01-01T00:00:00Z',
                    ],
                ],
                'error' => null,
            ])),
        ]);

        $this->artisan('wa:groups')
            ->assertExitCode(0)
            ->expectsOutputToContain('999@g.us')
            ->expectsOutputToContain('1 group(s).');
    }

    public function test_it_warns_when_no_groups_returned(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode(['success' => true, 'data' => [], 'error' => null])),
        ]);

        $this->artisan('wa:groups')
            ->assertExitCode(0)
            ->expectsOutputToContain('No groups found');
    }
}
