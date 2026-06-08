<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JigarDhulla\LaravelWhatsApp\Listening\Contracts\ListenerReporter;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Throwable;

/**
 * Renders listener progress to the console. Owns all verbosity gating:
 * -v reports batch counts and error traces, -vv every scanned message and the
 * full configuration, -vvv per-tick internal state.
 */
class ConsoleListenerReporter implements ListenerReporter
{
    private readonly Factory $components;

    public function __construct(private readonly OutputStyle $output)
    {
        $this->components = new Factory($output);
    }

    public function configuration(array $agents): void
    {
        if (! $this->output->isVeryVerbose()) {
            return;
        }

        $this->components->twoColumnDetail('wacli binary', (string) config('whatsapp-agent.wacli.binary'));
        $this->components->twoColumnDetail('wacli database', (string) config('whatsapp-agent.wacli.database'));
        $this->components->twoColumnDetail('Agents', (string) count($agents));

        foreach ($agents as $i => $agent) {
            $label = sprintf('  Agent #%d', $i + 1);
            $detail = sprintf(
                '%s — triggers: %s',
                class_basename((string) ($agent['agent'] ?? '?')),
                implode(', ', (array) ($agent['triggers'] ?? [])) ?: '(all)',
            );
            $this->components->twoColumnDetail($label, $detail);
        }
    }

    public function bookmarked(int $head): void
    {
        if ($this->output->isDebug()) {
            $this->components->twoColumnDetail('Bookmarked head rowid', (string) $head);
        }
    }

    public function listening(int $agentCount, int $jidCount, int $interval): void
    {
        $this->components->info(sprintf(
            'Listening with %d agent(s) across %d JID(s); polling every %ds.',
            $agentCount,
            $jidCount,
            $interval
        ));
    }

    public function tick(int $iteration): void
    {
        if (! $this->output->isDebug()) {
            return;
        }

        $lastRowid = (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 0);

        $this->output->writeln(sprintf(
            '  <fg=gray>[tick #%d] last_rowid=%d time=%s</>',
            $iteration, $lastRowid, now()->toTimeString()
        ));
    }

    public function batchFound(int $count): void
    {
        if ($this->output->isVerbose() && $count > 0) {
            $this->output->writeln(sprintf('  <fg=gray>%d new message(s) found</>', $count));
        }
    }

    public function messageDeferred(WhatsAppMessage $message): void
    {
        if ($this->output->isVeryVerbose()) {
            $this->output->writeln(sprintf(
                '  <fg=gray>[msg #%d] body not yet filled — deferred to ResolveWhatsAppMessage</>',
                $message->rowid,
            ));
        }
    }

    public function messageScanned(AgentRouter $router, WhatsAppMessage $message, array $matched, bool $quotesOwnMessage, ?string $body): void
    {
        if (! $this->output->isVeryVerbose()) {
            return;
        }

        $this->output->writeln(sprintf(
            '  <fg=gray>[msg #%d] %s in [%s] — agents matched: %d%s</>',
            $message->rowid,
            $message->sender_name ?: $message->sender_jid,
            $message->chat_name ?: $message->chat_jid,
            count($matched),
            $quotesOwnMessage ? ' (reply to agent)' : ''
        ));

        if (count($matched) === 0) {
            $this->showMatchDiagnostics($router, (string) $message->chat_jid, $body);
        }
    }

    public function jobDispatched(WhatsAppMessage $message, string $agentClass): void
    {
        if ($this->output->isVeryVerbose()) {
            $this->output->writeln(sprintf(
                '  <fg=gray>[msg #%d] dispatched job → %s</>',
                $message->rowid,
                class_basename($agentClass),
            ));
        }
    }

    public function iterationFailed(Throwable $e): void
    {
        $this->components->warn('Polling iteration failed: '.$e->getMessage());

        // -v: show exception trace
        if ($this->output->isVerbose()) {
            $this->output->writeln('<fg=gray>'.$e->getTraceAsString().'</>');
        }
    }

    private function showMatchDiagnostics(AgentRouter $router, string $chatJid, ?string $body): void
    {
        $preview = $body === null || $body === '' ? '(empty)' : Str::limit($body, 120);
        $this->output->writeln(sprintf('  <fg=gray>    body: %s</>', $preview));

        foreach ($router->diagnose($chatJid, $body) as $i => $d) {
            $this->output->writeln(sprintf(
                '  <fg=gray>    agent #%d %s — scope=%s trigger=%s (triggers: %s)</>',
                $i + 1,
                class_basename($d['agent']),
                $d['scope_matches'] ? 'ok' : 'no',
                $d['triggers_match'] ? 'ok' : 'no',
                implode(', ', $d['triggers']) ?: '(all)',
            ));
        }
    }
}
