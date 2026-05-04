<?php

declare(strict_types=1);

namespace JigarDhulla\LaravelWhatsApp\Agents;

use JigarDhulla\LaravelWhatsApp\Traits\RemembersWhatsAppConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class WhatsAppAgent implements Agent, Conversational
{
    use Promptable, RemembersWhatsAppConversations;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful AI agent reachable on WhatsApp. Reply concisely.';
    }
}
