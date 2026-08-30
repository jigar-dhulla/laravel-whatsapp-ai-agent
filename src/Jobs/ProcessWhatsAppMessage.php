<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use JigarDhulla\LaravelWhatsApp\Conversation\GroupParticipantFormatter;
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
        public readonly Carbon $ts,
        public readonly array $attachments = [],
        public readonly ?string $replyToMsgId = null,
        public readonly bool $mentionSender = false,
        public readonly ?string $attachment = null,
        public readonly ?string $attachmentCaption = null,
    ) {}

    public function handle(Wacli $wacli): void
    {
        /** @var Agent $agent */
        $agent = app($this->agentClass);

        $body = $this->body;

        if (in_array(RemembersWhatsAppConversations::class, class_uses_recursive($agent), true)) {
            $agent->forChat($this->chatJid, $this->senderJid, $this->senderName);
            $body = $agent->participantFormatter()->prefix($this->chatJid, $this->senderName, $this->senderJid, $this->ts, $body);
        }

        $response = $agent->prompt($body, attachments: $this->attachments);

        $reply = $response->text;

        if ($reply === '') {
            return;
        }

        $mentions = [];

        if ($this->shouldMentionSender()) {
            $mentions[] = (string) $this->senderJid;
            $reply = $this->prependMentionToken($reply);
        }

        [$isOk, , $errorOutput] = $wacli->send(
            $this->chatJid,
            $reply,
            replyTo: $this->replyToMsgId,
            mentions: $mentions,
            attachment: $this->attachment,
            attachmentCaption: $this->attachmentCaption,
        );

        if (! $isOk && $errorOutput !== '') {
            $this->fail();
        }
    }

    /**
     * Mentions only make sense in group chats — in a DM the recipient is the
     * sender, so tagging them adds nothing.
     */
    private function shouldMentionSender(): bool
    {
        return $this->mentionSender
            && $this->senderJid !== null
            && app(GroupParticipantFormatter::class)->isGroupChat($this->chatJid);
    }

    /**
     * Ensure the reply contains the sender's @<jid-user-part> token, which
     * WhatsApp requires in the text to render a mention.
     */
    private function prependMentionToken(string $reply): string
    {
        $token = '@'.Str::before((string) $this->senderJid, '@');

        return str_contains($reply, $token) ? $reply : "{$token} {$reply}";
    }
}
