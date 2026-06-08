<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Throwable;

#[Signature('wa:listen {--once : Run a single polling iteration and exit} {--max-iterations= : Stop after this many iterations}')]
#[Description('Poll the wacli sqlite DB for new messages, run them through the configured agents, and reply.')]
class ListenCommand extends Command
{
    public function handle(WhatsAppMessageReader $reader, Wacli $wacli): int
    {
        $agents = (array) config('whatsapp-agent.agents', []);

        if ($agents === []) {
            $this->components->error(
                'No agents configured. Run `php artisan vendor:publish --tag=whatsapp-agent-config` and edit config/whatsapp-agent.php.'
            );

            return self::FAILURE;
        }

        $router = AgentRouter::fromConfig();

        if ($router->allowedJids() === []) {
            $this->components->warn(
                'All configured agents have empty scope (no chats or groups). Edit config/whatsapp-agent.php to configure agent scopes.'
            );

            return self::SUCCESS;
        }

        $interval = max(1, (int) config('whatsapp-agent.polling.interval_seconds', 5));

        // -vv: show full configuration
        if ($this->output->isVeryVerbose()) {
            $this->showDebugInfo($agents);
        }

        $head = Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID) ?? $reader->bookmarkCurrentHead();

        // -vvv: show internal state
        if ($this->output->isDebug()) {
            $this->components->twoColumnDetail('Bookmarked head rowid', (string) $head);
        }

        $jids = $router->allowedJids();

        $this->components->info(sprintf(
            'Listening with %d agent(s) across %d JID(s); polling every %ds.',
            count($agents),
            count($jids),
            $interval
        ));

        $iterations = 0;
        $maxIterations = $this->option('once') ? 1 : ($this->option('max-iterations') !== null ? (int) $this->option('max-iterations') : null);

        while ($maxIterations === null || $iterations < $maxIterations) {
            $iterations++;

            // -vvv: show each poll tick
            if ($this->output->isDebug()) {
                $lastRowid = (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 0);
                $this->line(sprintf(
                    '  <fg=gray>[tick #%d] last_rowid=%d time=%s</>',
                    $iterations, $lastRowid, now()->toTimeString()
                ));
            }

            try {
                $this->processBatch($reader, $router);
            } catch (Throwable $e) {
                $this->components->warn('Polling iteration failed: '.$e->getMessage());

                // -v: show exception trace
                if ($this->output->isVerbose()) {
                    $this->line('<fg=gray>'.$e->getTraceAsString().'</>');
                }

                report($e);
            }

            if ($maxIterations !== null && $iterations >= $maxIterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function processBatch(WhatsAppMessageReader $reader, AgentRouter $router): void
    {
        $messages = $reader->fetchNew($router);

        $count = $messages->count();

        // -v: report how many messages were found each batch
        if ($this->output->isVerbose() && $count > 0) {
            $this->line(sprintf('  <fg=gray>%d new message(s) found</>', $count));
        }

        foreach ($messages as $message) {
            if ($message->hasPlaceholderBody()) {
                ResolveWhatsAppMessage::dispatch($message->rowid)->delay(2);

                if ($this->output->isVeryVerbose()) {
                    $this->line(sprintf(
                        '  <fg=gray>[msg #%d] body not yet filled — deferred to ResolveWhatsAppMessage</>',
                        $message->rowid,
                    ));
                }

                $reader->markProcessed($message->rowid);

                continue;
            }

            $body = $message->text ?: $message->display_text;
            $quotesOwnMessage = $message->quotesOwnMessage();
            $matched = $router->match((string) $message->chat_jid, $body, $quotesOwnMessage);

            // -vv: show every scanned message
            if ($this->output->isVeryVerbose()) {
                $this->line(sprintf(
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

            foreach ($matched as $agentConfig) {
                dispatch(ProcessWhatsAppMessage::forMessage($message, (string) $agentConfig['agent'], $body));

                // -vv: show that a job was dispatched
                if ($this->output->isVeryVerbose()) {
                    $this->line(sprintf(
                        '  <fg=gray>[msg #%d] dispatched job → %s</>',
                        $message->rowid,
                        class_basename((string) $agentConfig['agent']),
                    ));
                }
            }

            $reader->markProcessed($message->rowid);
        }
    }

    private function showMatchDiagnostics(AgentRouter $router, string $chatJid, ?string $body): void
    {
        $preview = $body === null || $body === '' ? '(empty)' : Str::limit($body, 120);
        $this->line(sprintf('  <fg=gray>    body: %s</>', $preview));

        foreach ($router->diagnose($chatJid, $body) as $i => $d) {
            $this->line(sprintf(
                '  <fg=gray>    agent #%d %s — scope=%s trigger=%s (triggers: %s)</>',
                $i + 1,
                class_basename($d['agent']),
                $d['scope_matches'] ? 'ok' : 'no',
                $d['triggers_match'] ? 'ok' : 'no',
                implode(', ', $d['triggers']) ?: '(all)',
            ));
        }
    }

    private function showDebugInfo(array $agents): void
    {
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
}
