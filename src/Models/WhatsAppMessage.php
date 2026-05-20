<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;
use Laravel\Ai\Files\Document;
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
 */
class WhatsAppMessage extends Model
{
    public const string MIME_TYPE_JPEG = 'image/jpeg';
    public const string MIME_TYPE_PDF = 'application/pdf';

    protected $connection = WhatsAppMessageReader::CONNECTION_NAME;

    protected $table = 'messages';

    protected $primaryKey = 'rowid';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'from_me' => 'boolean',
            'ts' => 'timestamp',
            'downloaded_at' => 'timestamp',
        ];
    }

    public function getAttachments(): array
    {
        if (is_null($this->media_type) || is_null($this->local_path) || !file_exists($this->local_path)) {
            return [];
        }

        /** @var \Laravel\Ai\Files\File $file */
        $file = match ($this->mime_type) {
            self::MIME_TYPE_JPEG => Image::fromPath($this->local_path),
            self::MIME_TYPE_PDF => Document::fromPath($this->local_path),
        };

        return [$file];
    }
}
