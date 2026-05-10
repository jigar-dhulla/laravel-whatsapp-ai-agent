<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

#[Signature('wa:chats {--all : Include groups in addition to DMs}')]
#[Description('List chat JIDs from the wacli store. Useful for populating the `chats` scope in config/whatsapp-agent.php.')]
class ChatsCommand extends Command
{
    public function handle(Wacli $wacli): int
    {
        $chats = $wacli->chats();

        if ($chats === []) {
            $this->components->warn('No chats found. Run `wacli sync` first or check `wa:status`.');

            return self::SUCCESS;
        }

        if (! $this->option('all')) {
            $chats = array_values(array_filter(
                $chats,
                fn (array $c) => ($c['kind'] ?? null) === 'dm',
            ));
        }

        $rows = array_map(
            fn (array $c) => [
                $c['jid'] ?? '',
                $c['kind'] ?? '',
                $c['name'] ?? '',
                $c['last_message_ts'] ?? '',
            ],
            $chats,
        );

        $this->table(['JID', 'Kind', 'Name', 'Last Message'], $rows);

        $this->components->info(sprintf('%d chat(s).', count($rows)));

        return self::SUCCESS;
    }
}
