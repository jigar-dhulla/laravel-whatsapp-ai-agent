<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

/**
 * Pauses execution between polling iterations. Abstracted so the loop can be
 * driven synchronously (without real delays) in tests.
 */
interface Sleeper
{
    public function sleep(int $seconds): void;
}
