<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use LaravelWhatsApp\Agents\WhatsAppAgent;
use LaravelWhatsApp\Services\Wacli;
use LaravelWhatsApp\Traits\RemembersWhatsAppConversations;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(
        public readonly string $chatJid,
        public readonly ?string $chatName,
        public readonly ?string $senderJid,
        public readonly ?string $senderName,
        public readonly string $body,
        public readonly string $agentClass,
        public readonly ?string $providerOverride = null,
        public readonly ?string $modelOverride = null,
    ) {}

    public function handle(Wacli $wacli): void
    {
        $wacli->waitUntilUnlocked();

        /** @var WhatsAppAgent|RemembersWhatsAppConversations $agent */
        $agent = app($this->agentClass);

        $provider = $this->providerOverride !== null
            ? Lab::tryFrom($this->providerOverride)
            : null;

        $response = $agent->forChat($this->chatJid, $this->senderJid)
            ->prompt(
                $this->body,
                provider: $provider,
                model: ($this->modelOverride !== null && $this->modelOverride !== '') ? $this->modelOverride : null,
            );

        $reply = $response->text;

        if ($reply === '') {
            Log::info('[whatsapp-agent] agent returned empty reply — skipping send', [
                'chat_jid' => $this->chatJid,
                'agent' => $this->agentClass,
                'sender' => $this->senderName ?? $this->senderJid,
            ]);

            return;
        }

        [$isOk, $output, $errorOutput] = $wacli->send($this->chatJid, $reply);

        Log::info(sprintf(
            '[whatsapp-agent] %s → [%s] %s (agent: %s)',
            $isOk ? 'replied' : 'send FAILED',
            $this->chatName ?? $this->chatJid,
            $this->senderName ?? $this->senderJid ?? '—',
            class_basename($this->agentClass),
        ), [
            'chat_jid' => $this->chatJid,
            'agent' => $this->agentClass,
            'ok' => $isOk,
            'output' => $output,
            'error_output' => $errorOutput,
        ]);

        if (! $isOk && $errorOutput !== '') {
            Log::warning('[whatsapp-agent] wacli send error: '.$errorOutput, [
                'chat_jid' => $this->chatJid,
            ]);
            $this->fail();
        }
    }
}
