<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use JigarDhulla\LaravelWhatsApp\Services\Wacli;
use JigarDhulla\LaravelWhatsApp\Services\WhatsAppMessageReader;

#[Signature('wa:status')]
#[Description('Show WhatsApp agent runtime status: wacli auth/sync state, configured agents, and last processed message.')]
class StatusCommand extends Command
{
    public function handle(Wacli $wacli): int
    {
        $doctor = $wacli->doctor();

        if ($doctor === null) {
            $this->components->error('Could not run `wacli doctor`. Is the wacli binary path configured correctly?');
        } else {
            $this->components->twoColumnDetail('Store', (string) ($doctor['store_dir'] ?? '—'));
            $this->components->twoColumnDetail('Authenticated', $this->yesNo($doctor['authenticated'] ?? false));
            $this->components->twoColumnDetail('Connected', $this->yesNo($doctor['connected'] ?? false));
            $this->components->twoColumnDetail('Lock held (syncing)', $this->yesNo($doctor['lock_held'] ?? false));
        }

        $agents = (array) config('whatsapp-agent.agents', []);

        $this->components->twoColumnDetail('Configured agents', (string) count($agents));

        foreach ($agents as $i => $agent) {
            $this->components->twoColumnDetail(
                sprintf('  Agent #%d', $i + 1),
                class_basename((string) ($agent['agent'] ?? '?'))
            );

            $triggers = implode(', ', (array) ($agent['triggers'] ?? [])) ?: '(all messages)';
            $this->components->twoColumnDetail('    Triggers', $triggers);

            $scope = array_merge((array) ($agent['chats'] ?? []), (array) ($agent['groups'] ?? []));
            $scopeStr = count($scope) > 0
                ? count($scope).' JID(s)'
                : '<comment>inactive — no scope configured</comment>';
            $this->components->twoColumnDetail('    Scope', $scopeStr);
        }

        $lastRowid = (int) Cache::get(WhatsAppMessageReader::CACHE_KEY_LAST_ROWID, 0);
        $this->components->twoColumnDetail('Last processed rowid', $lastRowid > 0 ? (string) $lastRowid : '—');

        return self::SUCCESS;
    }

    private function yesNo(mixed $value): string
    {
        return $value ? '<info>yes</info>' : '<comment>no</comment>';
    }
}
