<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Traits\RemembersWhatsAppConversations;
use Laravel\Ai\Contracts\Agent;

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
    ) {}

    public function handle(Wacli $wacli): void
    {
        /** @var Agent $agent */
        $agent = app($this->agentClass);

        if (in_array(RemembersWhatsAppConversations::class, class_uses_recursive($agent), true)) {
            $agent->forChat($this->chatJid, $this->senderJid);
        }

        $response = $agent->prompt($this->body);

        $reply = $response->text;

        if ($reply === '') {
            return;
        }

        [$isOk, , $errorOutput] = $wacli->send($this->chatJid, $reply);

        if (! $isOk && $errorOutput !== '') {
            $this->fail();
        }
    }
}
