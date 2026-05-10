<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;

#[Signature('wa:groups')]
#[Description('List group JIDs from the wacli store. Useful for populating the `groups` scope in config/whatsapp-agent.php.')]
class GroupsCommand extends Command
{
    public function handle(Wacli $wacli): int
    {
        $groups = $wacli->groups();

        if ($groups === []) {
            $this->components->warn('No groups found. Run `wacli sync` first or check `wa:status`.');

            return self::SUCCESS;
        }

        $rows = array_map(
            fn (array $g) => [
                $g['JID'] ?? '',
                $g['Name'] ?? '',
                $g['OwnerJID'] ?? '',
                $g['UpdatedAt'] ?? '',
            ],
            $groups,
        );

        $this->table(['JID', 'Name', 'Owner', 'Updated'], $rows);

        $this->components->info(sprintf('%d group(s).', count($rows)));

        return self::SUCCESS;
    }
}
