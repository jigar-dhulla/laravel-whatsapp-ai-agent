<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JigarDhulla\LaravelWhatsApp\Listening\ConsoleListenerReporter;
use JigarDhulla\LaravelWhatsApp\Listening\MessagePipeline;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Throwable;

#[Signature('wa:listen {--once : Run a single polling iteration and exit} {--max-iterations= : Stop after this many iterations}')]
#[Description('Poll the wacli sqlite DB for new messages, run them through the configured agents, and reply.')]
class ListenCommand extends Command
{
    public function handle(WhatsAppMessageReader $reader, MessagePipeline $pipeline): int
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
                $pipeline->process($reader, $router, $reporter);
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
}
