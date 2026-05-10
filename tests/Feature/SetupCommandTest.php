<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature;

use Illuminate\Console\Attributes\Signature;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Console\Commands\SetupCommand;
use JigarDhulla\LaravelWhatsApp\Tests\TestCase;

#[Signature('wa:setup')]
class TestSetupCommand extends SetupCommand
{
    protected function envPath(): string
    {
        return base_path('.env.test');
    }
}

class SetupCommandTest extends TestCase
{
    protected string $tempEnvPath;

    protected function setUp(): void
    {
        parent::setUp();
        Process::preventStrayProcesses();

        $this->tempEnvPath = base_path('.env.test');

        if (File::exists($this->tempEnvPath)) {
            File::delete($this->tempEnvPath);
        }

        $this->app->bind(SetupCommand::class, TestSetupCommand::class);
    }

    protected function tearDown(): void
    {
        if (File::exists($this->tempEnvPath)) {
            File::delete($this->tempEnvPath);
        }
        parent::tearDown();
    }

    public function test_it_successfully_sets_up_paths_with_valid_version(): void
    {
        Process::fake([
            '*' => function ($process) {
                $cmd = implode(' ', (array) $process->command);
                if (str_contains($cmd, 'which')) {
                    return Process::result(output: "/usr/local/bin/wacli\n");
                }
                if (str_contains($cmd, '--version')) {
                    return Process::result(output: "wacli 0.8.1\n");
                }
                if (str_contains($cmd, 'doctor')) {
                    return Process::result(output: json_encode([
                        'success' => true,
                        'data' => ['store_dir' => '/tmp/.wacli'],
                    ]));
                }

                return Process::result();
            },
        ]);

        $this->artisan('wa:setup')
            ->expectsQuestion('Path to the wacli binary', 'wacli')
            ->expectsQuestion('Path to the wacli store directory', '/tmp/.wacli')
            ->expectsQuestion('Path to the wacli sqlite database', '/tmp/.wacli/wacli.db')
            ->assertExitCode(0);

        $this->assertStringContainsString('WA_WACLI_BINARY=wacli', File::get($this->tempEnvPath));
    }

    public function test_it_fails_immediately_if_version_is_too_low(): void
    {
        Process::fake([
            '*' => function ($process) {
                $cmd = implode(' ', (array) $process->command);
                if (str_contains($cmd, 'which')) {
                    return Process::result(output: "/usr/local/bin/wacli\n");
                }
                if (str_contains($cmd, '--version')) {
                    return Process::result(output: "wacli 0.8.0\n");
                }

                return Process::result();
            },
        ]);

        $this->artisan('wa:setup')
            ->expectsQuestion('Path to the wacli binary', 'wacli')
            ->expectsOutputToContain('The wacli binary must be at least version 0.8.1. Found version 0.8.0.')
            ->assertExitCode(1);

        $this->assertFalse(File::exists($this->tempEnvPath));
    }
}
