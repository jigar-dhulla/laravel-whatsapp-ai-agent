<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

class WacliServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Process::preventStrayProcesses();
    }

    public function test_doctor_returns_decoded_data(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    'store_dir' => '/Users/test/.wacli',
                    'authenticated' => true,
                    'connected' => false,
                    'lock_held' => false,
                    'connection_state' => 'disconnected',
                    'linked_jid' => '911234567890@s.whatsapp.net',
                    'fts_enabled' => true,
                    'store' => [
                        'messages' => 2349,
                        'chats' => 1146,
                    ],
                ],
                'error' => null,
            ])),
        ]);

        $data = (new Wacli('/usr/local/bin/wacli'))->doctor();

        $this->assertSame('/Users/test/.wacli', $data['store_dir']);
        $this->assertTrue($data['authenticated']);
        $this->assertSame('911234567890@s.whatsapp.net', $data['linked_jid']);
    }

    public function test_wacli_exposes_doctor_properties(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    'store_dir' => '/Users/test/.wacli',
                    'authenticated' => true,
                    'connected' => true,
                    'lock_held' => false,
                    'connection_state' => 'connected',
                    'linked_jid' => '911234567890@s.whatsapp.net',
                    'fts_enabled' => true,
                    'store' => [
                        'messages' => 10,
                    ],
                ],
                'error' => null,
            ])),
        ]);

        $wacli = new Wacli('wacli');

        $this->assertTrue($wacli->isAuthenticated());
        $this->assertTrue($wacli->isConnected());
        $this->assertSame('connected', $wacli->getConnectionState());
        $this->assertSame('911234567890@s.whatsapp.net', $wacli->getLinkedJid());
        $this->assertSame('/Users/test/.wacli', $wacli->getStoreDir());
        $this->assertTrue($wacli->isFtsEnabled());
        $this->assertSame(['messages' => 10], $wacli->getStoreStats());

        // Test caching: doctor() should only be called once
        $wacli->isAuthenticated();
        Process::assertRanTimes(fn ($process) => str_contains(implode(' ', $process->command), 'doctor'), 1);
    }

    public function test_doctor_returns_null_when_command_fails(): void
    {
        Process::fake([
            '*' => Process::result(exitCode: 1),
        ]);

        $this->assertNull((new Wacli('wacli'))->doctor());
    }

    public function test_chats_returns_only_the_data_array(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    ['JID' => '123@s.whatsapp.net', 'Kind' => 'dm', 'Name' => 'Alice'],
                    ['JID' => '456@g.us', 'Kind' => 'group', 'Name' => 'Team'],
                ],
                'error' => null,
            ])),
        ]);

        $chats = (new Wacli('wacli'))->chats();

        $this->assertCount(2, $chats);
        $this->assertSame('Alice', $chats[0]['Name']);
    }

    public function test_groups_returns_empty_array_when_none_found(): void
    {
        Process::fake([
            '*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [],
                'error' => null,
            ])),
        ]);

        $this->assertSame([], (new Wacli('wacli'))->groups());
    }

    public function test_send_returns_success_tuple_on_zero_exit(): void
    {
        Process::fake([
            '*' => Process::result(output: '{"success":true}', exitCode: 0),
        ]);

        [$ok, $stdout, $stderr] = (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hello');

        $this->assertTrue($ok);
        $this->assertStringContainsString('{"success":true}', $stdout);
        $this->assertSame('', trim($stderr));
    }

    public function test_send_emits_reply_to_flag_when_provided(): void
    {
        Process::fake([
            '*' => Process::result(output: '{"success":true}', exitCode: 0),
        ]);

        (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hi', replyTo: 'MSG-ABC');

        Process::assertRan(function ($process) {
            $command = is_array($process->command) ? $process->command : explode(' ', $process->command);

            $index = array_search('--reply-to', $command, true);

            return $index !== false
                && ($command[$index + 1] ?? null) === 'MSG-ABC'
                && ! in_array('--reply-to-sender', $command, true);
        });
    }

    public function test_send_emits_reply_to_sender_flag_when_provided(): void
    {
        Process::fake([
            '*' => Process::result(output: '{"success":true}', exitCode: 0),
        ]);

        (new Wacli('wacli'))->send(
            '456@g.us',
            'Hi group',
            replyTo: 'MSG-XYZ',
            replyToSender: '789@s.whatsapp.net',
        );

        Process::assertRan(function ($process) {
            $command = is_array($process->command) ? $process->command : explode(' ', $process->command);

            $senderIndex = array_search('--reply-to-sender', $command, true);

            return $senderIndex !== false
                && ($command[$senderIndex + 1] ?? null) === '789@s.whatsapp.net';
        });
    }

    public function test_send_omits_reply_flags_when_not_provided(): void
    {
        Process::fake([
            '*' => Process::result(output: '{"success":true}', exitCode: 0),
        ]);

        (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hi');

        Process::assertRan(function ($process) {
            $command = is_array($process->command) ? $process->command : explode(' ', $process->command);

            return ! in_array('--reply-to', $command, true)
                && ! in_array('--reply-to-sender', $command, true);
        });
    }

    public function test_send_returns_failure_tuple_on_nonzero_exit(): void
    {
        Process::fake([
            '*' => Process::result(exitCode: 1, errorOutput: 'not connected'),
        ]);

        [$ok, $stdout, $stderr] = (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hello');

        $this->assertFalse($ok);
        $this->assertSame('', trim($stdout));
        $this->assertStringContainsString('not connected', $stderr);
    }

    public function test_locate_binary_returns_trimmed_path(): void
    {
        Process::fake([
            '*' => Process::result(output: "/usr/local/bin/wacli\n"),
        ]);

        $this->assertSame('/usr/local/bin/wacli', (new Wacli)->locateBinary());
    }

    public function test_locate_binary_returns_null_when_missing(): void
    {
        Process::fake([
            '*' => Process::result(exitCode: 1),
        ]);

        $this->assertNull((new Wacli)->locateBinary());
    }

    public function test_version_returns_only_version_number(): void
    {
        Process::fake([
            '*' => Process::result(output: "wacli 0.8.1\n"),
        ]);

        $this->assertSame('0.8.1', (new Wacli('wacli'))->version());
    }
}
