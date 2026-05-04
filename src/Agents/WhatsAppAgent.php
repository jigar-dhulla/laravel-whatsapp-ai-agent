<?php

declare(strict_types=1);

namespace LaravelWhatsApp\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use LaravelWhatsApp\Traits\RemembersConversations;
use Stringable;

class WhatsAppAgent implements Agent, Conversational
{
    use Promptable, RemembersConversations;

    public function instructions(): Stringable|string
    {
        return 'You are a helpful AI agent reachable on WhatsApp. Reply concisely.';
    }
}
