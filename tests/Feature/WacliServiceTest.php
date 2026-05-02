<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Tests\Feature;

use Illuminate\Support\Facades\Process;
use LaravelWhatsApp\Services\Wacli;
use LaravelWhatsApp\Tests\TestCase;

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
            '*doctor*' => Process::result(output: json_encode([
                'success' => true,
                'data' => [
                    'store_dir' => '/Users/test/.wacli',
                    'authenticated' => true,
                    'connected' => false,
                    'lock_held' => false,
                ],
                'error' => null,
            ])),
        ]);

        $data = (new Wacli('/usr/local/bin/wacli'))->doctor();

        $this->assertSame('/Users/test/.wacli', $data['store_dir']);
        $this->assertTrue($data['authenticated']);
    }

    public function test_doctor_returns_null_when_command_fails(): void
    {
        Process::fake([
            '*doctor*' => Process::result(exitCode: 1),
        ]);

        $this->assertNull((new Wacli('wacli'))->doctor());
    }

    public function test_chats_returns_only_the_data_array(): void
    {
        Process::fake([
            '*chats*list*' => Process::result(output: json_encode([
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
            '*groups*list*' => Process::result(output: json_encode([
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
            '*send*' => Process::result(output: '{"success":true}', exitCode: 0),
        ]);

        [$ok, $stdout, $stderr] = (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hello');

        $this->assertTrue($ok);
        $this->assertStringContainsString('{"success":true}', $stdout);
        $this->assertSame('', trim($stderr));
    }

    public function test_send_returns_failure_tuple_on_nonzero_exit(): void
    {
        Process::fake([
            '*send*' => Process::result(exitCode: 1, errorOutput: 'not connected'),
        ]);

        [$ok, $stdout, $stderr] = (new Wacli('wacli'))->send('123@s.whatsapp.net', 'Hello');

        $this->assertFalse($ok);
        $this->assertSame('', trim($stdout));
        $this->assertStringContainsString('not connected', $stderr);
    }

    public function test_locate_binary_returns_trimmed_path(): void
    {
        Process::fake([
            '*which*wacli*' => Process::result(output: "/usr/local/bin/wacli\n"),
        ]);

        $this->assertSame('/usr/local/bin/wacli', (new Wacli)->locateBinary());
    }

    public function test_locate_binary_returns_null_when_missing(): void
    {
        Process::fake([
            '*which*wacli*' => Process::result(exitCode: 1),
        ]);

        $this->assertNull((new Wacli)->locateBinary());
    }

    public function test_sync_returns_data_on_success(): void
    {
        Process::fake([
            '*sync*' => Process::result(output: json_encode([
                'success' => true,
                'data' => ['messages_stored' => 42, 'synced' => true],
            ])),
        ]);

        $result = (new Wacli('wacli'))->syncOnceExitIfIdleForFiveSeconds();

        $this->assertSame(42, $result['messages_stored']);
        $this->assertTrue($result['synced']);
    }

    public function test_sync_returns_zeros_on_failure(): void
    {
        Process::fake([
            '*sync*' => Process::result(exitCode: 1),
        ]);

        $result = (new Wacli('wacli'))->syncOnceExitIfIdleForFiveSeconds();

        $this->assertSame(0, $result['messages_stored']);
        $this->assertFalse($result['synced']);
    }
}
