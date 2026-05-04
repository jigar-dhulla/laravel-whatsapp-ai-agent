<?php

namespace LaravelWhatsApp\Traits;

use Laravel\Ai\Messages\Message;
use LaravelWhatsApp\Models\WhatsAppMessage;

trait RemembersConversations
{
    protected ?string $chatJid = null;

    protected ?string $senderJid = null;

    /**
     * Start a new conversation for the given user.
     */
    public function forChat($chatJid, $senderJid): static
    {
        $this->chatJid = $chatJid;
        $this->senderJid = $senderJid;

        return $this;
    }

    /**
     * Continue an existing conversation as the given user.
     */
    public function continue(string $chatJid, string $as): static
    {
        $this->chatJid = $chatJid;
        $this->senderJid = $as;

        return $this;
    }

    /**
     * Continue an existing conversation as the given user.
     */
    public function continueLastConversation(object $as): static
    {
        $this->senderJid = $as;

        $this->chatJid = WhatsAppMessage::query()->limit(1)->orderBy('ts', 'desc')->first()->chat_jid;

        return $this;
    }

    /**
     * Get the list of messages comprising the conversation so far.
     */
    public function messages(): iterable
    {
        if (! $this->chatJid) {
            return [];
        }

        return WhatsAppMessage::query()
            ->limit($this->maxConversationMessages())
            ->orderBy('ts', 'desc')
            ->get()
            ->map(function (WhatsAppMessage $message) {
                return new Message($message->sender_jid, $message->display_text ?? $message->text);
            });
    }

    /**
     * Get the maximum number of conversation messages to include in context.
     */
    protected function maxConversationMessages(): int
    {
        return 100;
    }

    /**
     * Get the UUID for the current conversation, if applicable.
     */
    public function currentConversation(): ?string
    {
        return $this->chatJid;
    }

    /**
     * Determine if the conversation has a participant and is thus being remembered.
     */
    public function hasConversationParticipant(): bool
    {
        return $this->senderJid !== null;
    }

    /**
     * Get the user having the current conversation.
     */
    public function conversationParticipant(): ?string
    {
        return $this->senderJid;
    }
}
