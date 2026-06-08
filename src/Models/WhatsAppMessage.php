<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Files\File;
use Laravel\Ai\Files\Image;

/**
 * @property int $rowid
 * @property string $chat_jid
 * @property string|null $chat_name
 * @property string $msg_id
 * @property string|null $sender_jid
 * @property string|null $sender_name
 * @property Carbon $ts
 * @property bool $from_me
 * @property string|null $text
 * @property string|null $display_text
 * @property string|null $media_type
 * @property string|null $media_caption
 * @property string|null $filename
 * @property string|null $mime_type
 * @property string|null $direct_path
 * @property string|null $media_key
 * @property string|null $file_sha256
 * @property string|null $file_enc_sha256
 * @property int|null $file_length
 * @property string|null $local_path
 * @property Carbon|null $downloaded_at
 * @property string|null $quoted_msg_id
 * @property string|null $quoted_sender_jid
 */
class WhatsAppMessage extends Model
{
    public const string MIME_TYPE_JPEG = 'image/jpeg';

    public const string MIME_TYPE_PDF = 'application/pdf';

    /**
     * Sentinel wacli writes to display_text when its async sync hasn't yet
     * resolved a protocol message's actual content. The row is updated in
     * place once wacli fills the body. See wacli/internal/app/sync.go.
     */
    public const string PLACEHOLDER_BODY = '(message)';

    protected $connection = WhatsAppMessageReader::CONNECTION_NAME;

    protected $table = 'messages';

    protected $primaryKey = 'rowid';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'ts' => 'datetime',
            'downloaded_at' => 'timestamp',
        ];
    }

    public function hasPlaceholderBody(): bool
    {
        return ($this->text ?: $this->display_text) === self::PLACEHOLDER_BODY;
    }

    /**
     * Whether this message replies to (quotes) a message we sent. wacli stores
     * our own outbound messages with from_me=true, so the quoted message being
     * one of ours is the authoritative signal — more reliable than comparing
     * quoted_sender_jid to the account JID, which wacli does not consistently
     * set to the linked account for outbound rows.
     */
    public function quotesOwnMessage(): bool
    {
        if ($this->quoted_msg_id === null || $this->quoted_msg_id === '') {
            return false;
        }

        return static::query()
            ->where('msg_id', $this->quoted_msg_id)
            ->where('from_me', true)
            ->exists();
    }

    /**
     * @return array|File[]
     */
    public function getAttachments(): array
    {
        if (is_null($this->media_type) || is_null($this->local_path) || ! file_exists($this->local_path)) {
            return [];
        }

        return match ($this->mime_type) {
            self::MIME_TYPE_JPEG => [Image::fromPath($this->local_path)],
            self::MIME_TYPE_PDF => [Document::fromPath($this->local_path)],
            default => []
        };
    }
}
