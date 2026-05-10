<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Services;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use JigarDhulla\LaravelWhatsApp\Exceptions\WacliException;
use RuntimeException;

class Wacli
{
    /** @var array<string, mixed>|null */
    protected ?array $doctorData = null;

    public function __construct(protected ?string $binary = null) {}

    public function binary(): string
    {
        return $this->binary
            ?: (string) (config('whatsapp-agent.wacli.binary') ?: 'wacli');
    }

    /**
     * Locate the wacli binary on PATH. Returns null when not found.
     */
    public function locateBinary(): ?string
    {
        $result = Process::run(['/usr/bin/env', 'which', 'wacli']);

        if (! $result->successful()) {
            return null;
        }

        $path = trim($result->output());

        return $path === '' ? null : $path;
    }

    /**
     * Run \`wacli doctor --json\` and return the decoded \`data\` payload, or null on failure.
     *
     * @return array<string, mixed>|null
     */
    public function doctor(bool $refresh = false): ?array
    {
        if ($refresh || $this->doctorData === null) {
            $data = $this->runJson(['doctor']);
            $this->doctorData = is_array($data) ? $data : null;
        }

        return $this->doctorData;
    }

    /**
     * Get Version
     * @return string
     */
    public function version(): string
    {
        $processedResult =  $this->runBinary(['--version']);
        return trim($processedResult->output());
    }

    /**
     * Check if wacli is currently holding a sync lock.
     */
    public function isLockHeld(): bool
    {
        $data = $this->doctor(refresh: true);

        return (bool) ($data['lock_held'] ?? false);
    }

    /**
     * Check if wacli is authenticated.
     */
    public function isAuthenticated(): bool
    {
        $data = $this->doctor();

        return (bool) ($data['authenticated'] ?? false);
    }

    /**
     * Check if wacli is connected.
     */
    public function isConnected(): bool
    {
        $data = $this->doctor();

        return (bool) ($data['connected'] ?? false);
    }

    /**
     * Get the connection state.
     */
    public function getConnectionState(): string
    {
        $data = $this->doctor();

        return (string) ($data['connection_state'] ?? 'unknown');
    }

    /**
     * Get the linked JID.
     */
    public function getLinkedJid(): ?string
    {
        $data = $this->doctor();

        return $data['linked_jid'] ?? null;
    }

    /**
     * Get the store directory.
     */
    public function getStoreDir(): ?string
    {
        $data = $this->doctor();

        return $data['store_dir'] ?? null;
    }

    /**
     * Check if FTS is enabled.
     */
    public function isFtsEnabled(): bool
    {
        $data = $this->doctor();

        return (bool) ($data['fts_enabled'] ?? false);
    }

    /**
     * Get store statistics.
     *
     * @return array<string, mixed>
     */
    public function getStoreStats(): array
    {
        $data = $this->doctor();

        return is_array($data['store'] ?? null) ? $data['store'] : [];
    }

    /**
     * Poll wacli until the sync lock is released, or max attempts exceeded.
     * If the lock is still held after max attempts, log a warning and proceed.
     */
    public function waitUntilUnlocked(int $maxAttempts = 30, int $sleepSeconds = 1): void
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            if (! $this->isLockHeld()) {
                return;
            }

            sleep($sleepSeconds);
        }

        throw new WacliException('Could not get the lock');
    }

    /**
     * List all known chats (DMs and groups) from the local DB.
     *
     * @return array<int, array<string, mixed>>
     */
    public function chats(): array
    {
        $data = $this->runJson(['chats', 'list']);

        return is_array($data) ? $data : [];
    }

    /**
     * List joined groups from the local DB.
     *
     * @return array<int, array<string, mixed>>
     */
    public function groups(): array
    {
        $data = $this->runJson(['groups', 'list']);

        return is_array($data) ? $data : [];
    }

    /**
     * Send a text message to a JID via the wacli binary.
     *
     * Returns [success, stdout, stderr].
     *
     * @return array{0: bool, 1: string, 2: string}
     */
    public function send(string $jid, string $message): array
    {
        $result = $this->runBinary([
            'send', 'text',
            '--to', $jid,
            '--message', $message,
            '--json',
        ]);

        return [$result->successful(), $result->output(), $result->errorOutput()];
    }

    /**
     * Run an initial sync with a hard timeout so \`wa:install\` never hangs
     * when messages keep arriving and the idle timer never fires.
     *
     * Returns whatever wacli managed to store before the timeout/exit.
     *
     * @return array{messages_stored: int, synced: bool}
     */
    public function syncOnceExitIfIdleForFiveSeconds(): array
    {
        $result = $this->runBinary(['sync', '--once', '--idle-exit', '5s', '--json'], timeout: 30);

        if (! $result->successful()) {
            return ['messages_stored' => 0, 'synced' => false];
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded) || ($decoded['success'] ?? false) !== true) {
            return ['messages_stored' => 0, 'synced' => false];
        }

        $data = $decoded['data'] ?? [];

        return [
            'messages_stored' => (int) ($data['messages_stored'] ?? 0),
            'synced' => true,
        ];
    }

    protected function runBinary(array $arguments, ?int $timeout = null): ProcessResult
    {
        $store = config('whatsapp-agent.wacli.store');

        $command = [$this->binary()];

        if ($store) {
            $command = [...$command, '--store', $store];
        }

        $process = Process::command([...$command, ...$arguments]);

        if ($timeout !== null) {
            $process = $process->timeout($timeout);
        }

        return $process->run();
    }

    /**
     * Run a wacli subcommand with \`--json\` and return the decoded \`data\` field.
     */
    protected function runJson(array $arguments): mixed
    {
        $result = $this->runBinary([...$arguments, '--json']);

        if (! $result->successful()) {
            return null;
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException('wacli returned invalid JSON: '.$result->output());
        }

        if (($decoded['success'] ?? false) !== true) {
            return null;
        }

        return $decoded['data'] ?? null;
    }
}
