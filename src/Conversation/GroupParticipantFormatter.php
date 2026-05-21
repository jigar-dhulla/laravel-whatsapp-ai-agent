<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Conversation;

use Illuminate\Support\Str;

/**
 * Formats participant identity for group chats so the LLM can tell speakers apart.
 *
 * Has no awareness of the conversation memory trait or Eloquent — it's pure logic
 * that decides how to label a sender and what context note to attach in a group.
 *
 * Customize the format by binding a subclass in the service container:
 *     $this->app->bind(GroupParticipantFormatter::class, MyFormatter::class);
 */
class GroupParticipantFormatter
{
    public function isGroupChat(?string $chatJid): bool
    {
        return $chatJid !== null && str_ends_with($chatJid, '@g.us');
    }

    /**
     * Return the bracketed sender label for a group message, or null when no
     * label should be applied (DMs, or when overridden to opt out).
     */
    public function senderLabel(?string $chatJid, ?string $senderName, ?string $senderJid): ?string
    {
        if (! $this->isGroupChat($chatJid)) {
            return null;
        }

        $label = $this->blankToNull($senderName)
            ?? $this->phoneFromJid($senderJid)
            ?? 'Unknown';

        return "[{$label}]";
    }

    private function blankToNull(?string $value): ?string
    {
        return ($value === null || $value === '') ? null : $value;
    }

    private function phoneFromJid(?string $senderJid): ?string
    {
        if ($senderJid === null) {
            return null;
        }

        return $this->blankToNull(Str::before($senderJid, '@'));
    }

    /**
     * Prefix the given content with the sender label when one applies.
     * Returns the content unchanged for DMs.
     */
    public function prefix(?string $chatJid, ?string $senderName, ?string $senderJid, string $content): string
    {
        $label = $this->senderLabel($chatJid, $senderName, $senderJid);

        return $label === null ? $content : "{$label} {$content}";
    }

    /**
     * One-turn context note explaining the [Name] prefix convention to the LLM.
     * Returns null in DMs (or when overridden to suppress).
     */
    public function contextNote(?string $chatJid): ?string
    {
        if (! $this->isGroupChat($chatJid)) {
            return null;
        }

        return '[Note: This is a WhatsApp group chat. Each user message below is '
            .'prefixed with the sender\'s name in square brackets, e.g. "[Alice] hello". '
            .'Address participants by their names. Do not prefix your own replies.]';
    }

    /**
     * Synthetic Assistant acknowledgement paired with contextNote() so the
     * conversation given to the LLM preserves strict user/assistant alternation.
     * Returns null in DMs.
     */
    public function contextAck(?string $chatJid): ?string
    {
        return $this->isGroupChat($chatJid) ? 'Understood.' : null;
    }
}
