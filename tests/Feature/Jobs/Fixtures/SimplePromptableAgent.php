<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Tests\Feature\Jobs\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

class SimplePromptableAgent implements Agent
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return 'You are a simple promptable agent without WhatsApp conversation memory.';
    }
}
