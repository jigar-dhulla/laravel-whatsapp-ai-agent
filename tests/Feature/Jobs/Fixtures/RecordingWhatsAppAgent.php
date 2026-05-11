<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs\Fixtures;

use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;

class RecordingWhatsAppAgent extends WhatsAppAgent
{
    /** @var array<int, array{0: string, 1: ?string}> */
    public static array $forChatCalls = [];

    public function forChat(string $chatJid, ?string $senderJid = null): static
    {
        self::$forChatCalls[] = [$chatJid, $senderJid];

        return parent::forChat($chatJid, $senderJid);
    }

    public static function reset(): void
    {
        self::$forChatCalls = [];
    }
}
