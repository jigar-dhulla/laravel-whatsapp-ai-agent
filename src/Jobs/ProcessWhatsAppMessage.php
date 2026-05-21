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
use Laravel\Ai\Files\File;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  array<int, File>  $attachments
     */
    public function __construct(
        public readonly string $chatJid,
        public readonly ?string $chatName,
        public readonly ?string $senderJid,
        public readonly ?string $senderName,
        public readonly string $body,
        public readonly string $agentClass,
        public readonly array $attachments = [],
        public readonly ?string $replyToMsgId = null,
    ) {}

    public function handle(Wacli $wacli): void
    {
        /** @var Agent $agent */
        $agent = app($this->agentClass);

        $body = $this->body;

        if (in_array(RemembersWhatsAppConversations::class, class_uses_recursive($agent), true)) {
            $agent->forChat($this->chatJid, $this->senderJid);
            $body = $agent->participantFormatter()->prefix($this->chatJid, $this->senderName, $this->senderJid, $body);
        }

        $response = $agent->prompt($body, attachments: $this->attachments);

        $reply = $response->text;

        if ($reply === '') {
            return;
        }

        [$isOk, , $errorOutput] = $wacli->send(
            $this->chatJid,
            $reply,
            replyTo: $this->replyToMsgId,
        );

        if (! $isOk && $errorOutput !== '') {
            $this->fail();
        }
    }
}
