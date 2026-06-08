<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Listening;

class NativeSleeper implements Sleeper
{
    public function sleep(int $seconds): void
    {
        sleep($seconds);
    }
}
