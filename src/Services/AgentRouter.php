<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Services;

use Illuminate\Support\Str;

final class AgentRouter
{
    /**
     * @param  array<int, array<string, mixed>>  $agents
     */
    public function __construct(private readonly array $agents) {}

    public static function fromConfig(): self
    {
        return new self((array) config('whatsapp-agent.agents', []));
    }

    /**
     * Returns all agent config entries whose scope contains $chatJid and whose
     * triggers match $body. An agent with empty chats AND groups is inactive
     * and never matches.
     *
     * @return array<int, array<string, mixed>>
     */
    public function match(string $chatJid, ?string $body): array
    {
        if ($body === null || trim($body) === '') {
            return [];
        }

        return array_values(array_filter(
            $this->agents,
            fn (array $a) => $this->scopeMatches($a, $chatJid) && $this->triggersMatch($a, $body)
        ));
    }

    /**
     * Deduped union of all per-agent chats + groups. Used to determine which
     * JIDs WhatsAppMessageReader should query.
     *
     * @return array<int, string>
     */
    public function allowedJids(): array
    {
        $jids = [];

        foreach ($this->agents as $agent) {
            foreach ((array) ($agent['chats'] ?? []) as $jid) {
                $jids[] = (string) $jid;
            }
            foreach ((array) ($agent['groups'] ?? []) as $jid) {
                $jids[] = (string) $jid;
            }
        }

        return array_values(array_unique($jids));
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function scopeMatches(array $a, string $chatJid): bool
    {
        $chats = (array) ($a['chats'] ?? []);
        $groups = (array) ($a['groups'] ?? []);

        if ($chats === [] && $groups === []) {
            return false;
        }

        return in_array($chatJid, $chats, true) || in_array($chatJid, $groups, true);
    }

    /**
     * @param  array<string, mixed>  $a
     */
    private function triggersMatch(array $a, string $body): bool
    {
        $triggers = array_values(array_filter((array) ($a['triggers'] ?? [])));

        if ($triggers === []) {
            return true;
        }

        foreach ($triggers as $trigger) {
            if (Str::contains($body, (string) $trigger, ignoreCase: true)) {
                return true;
            }
        }

        return false;
    }
}
