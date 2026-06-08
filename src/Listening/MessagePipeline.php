<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

use JigarDhulla\LaravelWhatsApp\Jobs\ProcessWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Jobs\ResolveWhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Listening\Contracts\ListenerReporter;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;

/**
 * Processes one batch of newly-arrived messages: defers rows whose body has not
 * yet been synced, routes the rest through the configured agents, and dispatches
 * a job per match. Stateless; progress is emitted through the reporter.
 */
class MessagePipeline
{
    public function process(WhatsAppMessageReader $reader, AgentRouter $router, ListenerReporter $reporter): void
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
