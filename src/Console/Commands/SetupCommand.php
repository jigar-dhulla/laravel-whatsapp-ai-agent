<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

#[Signature('wa:setup')]
#[Description('Configure the WhatsApp agent: detect wacli paths and write to .env.')]
class SetupCommand extends Command
{
    public function handle(Wacli $wacli): int
    {
        info('Welcome — let\'s configure your WhatsApp agent.');

        [$binary, $store, $database] = $this->resolveWacliPaths($wacli);

        $this->writeEnvVar('WA_WACLI_BINARY', $binary);
        $this->writeEnvVar('WA_WACLI_STORE', $store);
        $this->writeEnvVar('WA_WACLI_DATABASE', $database);

        info('Environment variables written to .env.');
        info('Publish the config to customise agents: php artisan vendor:publish --tag=whatsapp-agent-config');

        return self::SUCCESS;
    }

    private function writeEnvVar(string $key, string $value): void
    {
        $envPath = $this->laravel->basePath('.env');

        if (! file_exists($envPath)) {
            file_put_contents($envPath, '');
        }

        $contents = file_get_contents($envPath);
        $quotedValue = str_contains($value, ' ') ? "\"{$value}\"" : $value;
        $pattern = "/^{$key}=.*/m";

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, "{$key}={$quotedValue}", $contents);
        } else {
            $contents .= "\n{$key}={$quotedValue}";
        }

        file_put_contents($envPath, $contents);
    }

    /**
     * Detect the wacli binary, store directory, and sqlite database path.
     *
     * @return array{0: string, 1: string, 2: string}
     */
    private function resolveWacliPaths(Wacli $wacli): array
    {
        $detected = $wacli->locateBinary();

        if ($detected !== null) {
            note("Detected wacli binary at {$detected}");
        } else {
            warning('Could not locate the wacli binary on PATH.');
        }

        $binary = text(
            label: 'Path to the wacli binary',
            default: $detected ?? 'wacli',
            required: true,
        );

        $probe = (new Wacli($binary))->doctor();

        $detectedStore = is_array($probe) ? Arr::get($probe, 'store_dir') : null;

        if (is_string($detectedStore) && $detectedStore !== '') {
            note("Detected wacli store at {$detectedStore}");
        } else {
            warning('Could not auto-detect the wacli store via `wacli doctor`.');
        }

        $store = text(
            label: 'Path to the wacli store directory',
            placeholder: '/Users/you/.wacli',
            default: (is_string($detectedStore) ? $detectedStore : ''),
            required: true,
        );

        $database = text(
            label: 'Path to the wacli sqlite database',
            default: rtrim($store, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'wacli.db',
            required: true,
        );

        return [$binary, $store, $database];
    }
}
