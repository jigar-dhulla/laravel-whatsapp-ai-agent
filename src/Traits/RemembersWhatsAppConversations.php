<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Traits;

use JigarDhulla\LaravelWhatsApp\Conversation\GroupParticipantFormatter;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;

trait RemembersWhatsAppConversations
{
    protected ?string $chatJid = null;

    protected ?string $senderJid = null;

    protected ?string $senderName = null;

    /**
     * Start a conversation in the given chat, optionally scoped to a sender JID
     * and their display name.
     */
    public function forChat(string $chatJid, ?string $senderJid = null, ?string $senderName = null): static
    {
        $this->chatJid = $chatJid;
        $this->senderJid = $senderJid;
        $this->senderName = $senderName;

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

        $formatter = $this->participantFormatter();
        $chatJid = $this->chatJid;

        $history = WhatsAppMessage::query()
            ->where('chat_jid', $chatJid)
            ->limit($this->maxConversationMessages())
            ->orderBy('ts', 'desc')
            ->get()
            ->reverse()
            ->values()
            ->map(function (WhatsAppMessage $message) use ($formatter, $chatJid) {
                $content = (string) ($message->text ?: $message->display_text);

                if (! $message->from_me) {
                    $content = $formatter->prefix($chatJid, $message->sender_name, $message->sender_jid, $message->ts, $content);
                }

                return new Message($this->determineMessageRole($message), $content);
            })
            ->all();

        $note = $formatter->contextNote($chatJid);

        if ($note !== null) {
            $preamble = [new Message(MessageRole::User, $note)];
            $ack = $formatter->contextAck($chatJid);

            if ($ack !== null) {
                $preamble[] = new Message(MessageRole::Assistant, $ack);
            }

            $history = [...$preamble, ...$history];
        }

        return $history;
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

    /**
     * Get the display name of the user having the current conversation, when known.
     */
    public function conversationParticipantName(): ?string
    {
        return $this->senderName;
    }

    /**
     * Resolve the formatter used to label participants and generate the group
     * context preamble. Override in your agent — or bind a subclass of
     * GroupParticipantFormatter in the service container — to customize.
     */
    public function participantFormatter(): GroupParticipantFormatter
    {
        return app(GroupParticipantFormatter::class);
    }

    private function determineMessageRole(WhatsAppMessage $message): MessageRole
    {
        return $message->from_me
            ? MessageRole::Assistant
            : MessageRole::User;
    }
}
