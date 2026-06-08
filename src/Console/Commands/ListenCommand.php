<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Listening\ConsoleListenerReporter;
use JigarDhulla\LaravelWhatsApp\Listening\Contracts\ListenerReporter;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Throwable;

#[Signature('wa:listen {--once : Run a single polling iteration and exit} {--max-iterations= : Stop after this many iterations}')]
#[Description('Poll the wacli sqlite DB for new messages, run them through the configured agents, and reply.')]
class ListenCommand extends Command
{
    public function handle(WhatsAppMessageReader $reader): int
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

        $reporter = new ConsoleListenerReporter($this->output);

        $reporter->configuration($agents);

        $head = Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID) ?? $reader->bookmarkCurrentHead();

        $reporter->bookmarked((int) $head);

        $jids = $router->allowedJids();

        $reporter->listening(count($agents), count($jids), $interval);

        $iterations = 0;
        $maxIterations = $this->option('once') ? 1 : ($this->option('max-iterations') !== null ? (int) $this->option('max-iterations') : null);

        while ($maxIterations === null || $iterations < $maxIterations) {
            $iterations++;

            $reporter->tick($iterations);

            try {
                $this->processBatch($reader, $router, $reporter);
            } catch (Throwable $e) {
                $reporter->iterationFailed($e);

                report($e);
            }

            if ($maxIterations !== null && $iterations >= $maxIterations) {
                break;
            }

            sleep($interval);
        }

        return self::SUCCESS;
    }

    private function processBatch(WhatsAppMessageReader $reader, AgentRouter $router, ListenerReporter $reporter): void
    {
        $messages = $reader->fetchNew($router);

        $reporter->batchFound($messages->count());

        foreach ($messages as $message) {
            if ($message->hasPlaceholderBody()) {
                ResolveWhatsAppMessage::dispatch($message->rowid)->delay(2);

                $reporter->messageDeferred($message);

                $reader->markProcessed($message->rowid);

                continue;
            }

            $body = $message->text ?: $message->display_text;
            $quotesOwnMessage = $message->quotesOwnMessage();
            $matched = $router->match((string) $message->chat_jid, $body, $quotesOwnMessage);

            $reporter->messageScanned($router, $message, $matched, $quotesOwnMessage, $body);

            foreach ($matched as $agentConfig) {
                dispatch(ProcessWhatsAppMessage::forMessage($message, (string) $agentConfig['agent'], $body));

                $reporter->jobDispatched($message, (string) $agentConfig['agent']);
            }

            $reader->markProcessed($message->rowid);
        }
    }
}
