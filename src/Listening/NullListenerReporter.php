<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

use JigarDhulla\LaravelWhatsApp\Listening\Contracts\ListenerReporter;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use Throwable;

/**
 * Discards every event. Used when the pipeline runs outside an interactive
 * console (queued jobs, tests) so callers need not special-case reporting.
 */
class NullListenerReporter implements ListenerReporter
{
    public function configuration(array $agents): void {}

    public function bookmarked(int $head): void {}

    public function listening(int $agentCount, int $jidCount, int $interval): void {}

    public function tick(int $iteration): void {}

    public function batchFound(int $count): void {}

    public function messageDeferred(WhatsAppMessage $message): void {}

    public function messageScanned(AgentRouter $router, WhatsAppMessage $message, array $matched, bool $quotesOwnMessage, ?string $body): void {}

    public function jobDispatched(WhatsAppMessage $message, string $agentClass): void {}

    public function iterationFailed(Throwable $e): void {}
}
