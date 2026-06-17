<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs\Fixtures;

use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;

class RecordingWhatsAppAgent extends WhatsAppAgent
{
    /** @var array<int, array{0: string, 1: ?string, 2: ?string}> */
    public static array $forChatCalls = [];

    public function forChat(string $chatJid, ?string $senderJid = null, ?string $senderName = null): static
    {
        self::$forChatCalls[] = [$chatJid, $senderJid, $senderName];

        return parent::forChat($chatJid, $senderJid, $senderName);
    }

    public static function reset(): void
    {
        self::$forChatCalls = [];
    }
}
