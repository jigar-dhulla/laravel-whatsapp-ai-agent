<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use JigarDhulla\LaravelWhatsApp\Models\WhatsAppMessage;

class WhatsAppMessageReader
{
    public const string CACHE_KEY_LAST_ROWID = 'whatsapp-agent:last_rowid';

    public const string CONNECTION_NAME = 'whatsapp_agent';

    /**
     * Fetch new messages newer than the last processed rowid.
     * Returns rows ordered by rowid asc.
     *
     * @return Collection<int, WhatsAppMessage>
     */
    public function fetchNew(int $limit = 50): Collection
    {
        $lastRowid = (int) Cache::get(self::CACHE_KEY_LAST_ROWID, 0);

        return WhatsAppMessage::query()
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
