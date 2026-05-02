<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use LaravelWhatsApp\Models\WhatsAppMessage;

class WhatsAppMessageReader
{
    public const string CACHE_KEY_LAST_ROWID = 'whatsapp-agent:last_rowid';

    public const string CONNECTION_NAME = 'whatsapp_agent';

    /**
     * Fetch new inbound messages newer than the last processed rowid, scoped
     * to the configured chats and groups. Returns rows ordered by rowid asc.
     *
     * @return Collection<int, WhatsAppMessage>
     */
    public function fetchNew(AgentRouter $router, int $limit = 50): Collection
    {
        $lastRowid = (int) Cache::get(self::CACHE_KEY_LAST_ROWID, 0);

        $jids = $router->allowedJids();

        if ($jids === []) {
            return collect();
        }

        return WhatsAppMessage::query()->whereIn('chat_jid', $jids)
            ->where('from_me', false)
            ->where('rowid', '>', $lastRowid)
            ->orderBy('rowid')
            ->limit($limit)
            ->get();
    }

    public function markProcessed(int $rowid): void
    {
        Cache::put(self::CACHE_KEY_LAST_ROWID, $rowid);
    }

    /**
     * Initialize the bookmark to the current head of the message table so
     * the listener only responds to messages received from now on.
     */
    public function bookmarkCurrentHead(): int
    {
        $head = (int) (WhatsAppMessage::query()->max('rowid') ?? 0);

        $this->markProcessed($head);

        return $head;
    }
}
