<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening\Contracts;

use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use Throwable;

/**
 * Receives progress events from the listener pipeline. Implementations decide
 * how (and whether) to surface them; the pipeline stays output-agnostic.
 */
interface ListenerReporter
{
    /**
     * @param  array<int, array<string, mixed>>  $agents
     */
    public function configuration(array $agents): void;

    public function bookmarked(int $head): void;

    public function listening(int $agentCount, int $jidCount, int $interval): void;

    public function tick(int $iteration): void;

    public function batchFound(int $count): void;

    public function messageDeferred(WhatsAppMessage $message): void;

    /**
     * @param  array<int, array<string, mixed>>  $matched
     */
    public function messageScanned(AgentRouter $router, WhatsAppMessage $message, array $matched, bool $quotesOwnMessage, ?string $body): void;

    public function jobDispatched(WhatsAppMessage $message, string $agentClass): void;

    public function iterationFailed(Throwable $e): void;
}
