<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Traits;

use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use LaravelWhatsApp\Models\WhatsAppMessage;

trait RemembersWhatsAppConversations
{
    protected ?string $chatJid = null;

    protected ?string $senderJid = null;

    /**
     * Start a conversation in the given chat, optionally scoped to a sender JID.
     */
    public function forChat(string $chatJid, ?string $senderJid = null): static
    {
        $this->chatJid = $chatJid;
        $this->senderJid = $senderJid;

        return $this;
    }

    /**
     * Continue an existing conversation in the given chat as the given sender.
     */
    public function continue(string $chatJid, string $senderJid): static
    {
        $this->chatJid = $chatJid;
        $this->senderJid = $senderJid;

        return $this;
    }

    /**
     * Resume the conversation from the most recent chat the given sender participated in.
     */
    public function continueLastConversation(string $senderJid): static
    {
        $this->senderJid = $senderJid;

        $this->chatJid = WhatsAppMessage::query()
            ->where('sender_jid', $senderJid)
            ->orderBy('ts', 'desc')
            ->value('chat_jid');

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
            ->where('chat_jid', $this->chatJid)
            ->limit($this->maxConversationMessages())
            ->orderBy('ts', 'desc')
            ->get()
            ->reverse()
            ->values()
            ->map(function (WhatsAppMessage $message) {
                return new Message($this->determineMessageRole($message), $message->display_text ?? $message->text);
            })
            ->all();
    }

    /**
     * Get the maximum number of conversation messages to include in context.
     * Override in your agent class to set a per-agent limit.
     */
    protected function maxConversationMessages(): int
    {
        return (int) config('whatsapp-agent.conversation.history_limit', 100);
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

    private function determineMessageRole(WhatsAppMessage $message): MessageRole
    {
        return $message->from_me
            ? MessageRole::Assistant
            : MessageRole::User;
    }
}
