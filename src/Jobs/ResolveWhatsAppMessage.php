<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use JigarDhulla\LaravelWhatsApp\Services\AgentRouter;

class ResolveWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $backoff = 2;

    public function __construct(public readonly int $rowid) {}

    public function handle(): void
    {
        $message = WhatsAppMessage::query()->find($this->rowid);

        if ($message === null) {
            return;
        }

        if ($message->hasPlaceholderBody()) {
            $this->release($this->backoff);

            return;
        }

        $body = $message->text ?: $message->display_text;

        $matched = AgentRouter::fromConfig()->match((string) $message->chat_jid, $body);

        foreach ($matched as $agentConfig) {
            dispatch(ProcessWhatsAppMessage::forMessage($message, (string) $agentConfig['agent'], $body));
        }
    }
}
