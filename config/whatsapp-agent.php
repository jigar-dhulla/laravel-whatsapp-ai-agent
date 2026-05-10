<?php

declare(strict_types=1);
use JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent;

return [

    /*
    |--------------------------------------------------------------------------
    | wacli Integration
    |--------------------------------------------------------------------------
    |
    | Paths to the wacli binary and its sqlite database. Both are typically
    | populated by `php artisan wa:setup`, which auto-detects them via
    | `wacli doctor --json` and falls back to prompting the user.
    |
    */

    'wacli' => [
        'binary' => env('WA_WACLI_BINARY', 'wacli'),
        'database' => env('WA_WACLI_DATABASE'),
        'store' => env('WA_WACLI_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agents
    |--------------------------------------------------------------------------
    |
    | Each entry defines one AI agent. A message is dispatched to every agent
    | whose scope (chats + groups) contains the sender's JID and whose triggers
    | match the message body.
    |
    | - agent:    Laravel AI SDK Agent Class. Configure provider, model, and
    |             system prompt via the class's attributes and methods. See:
    |             https://laravel.com/docs/13.x/ai-sdk#agent-configuration
    | - triggers: Phrases that activate this agent (case-insensitive substring
    |             match). Empty array matches every message in scope.
    | - chats:    DM JIDs this agent listens to. MUST be non-empty unless
    |             groups is non-empty — empty scope means the agent is inactive.
    | - groups:   Group JIDs this agent listens to.
    |
    | The union of all agents' chats + groups determines which JIDs are polled.
    |
    */

    'agents' => [
        [
            'agent' => WhatsAppAgent::class,
            'triggers' => [],
            'chats' => [],
            'groups' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling
    |--------------------------------------------------------------------------
    |
    | How often the message reader polls wacli's sqlite database for new
    | messages, in seconds.
    |
    */

    'polling' => [
        'interval_seconds' => (int) env('WA_POLLING_INTERVAL', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversation History
    |--------------------------------------------------------------------------
    |
    | Maximum number of past messages included in each agent's context window.
    | The most recent N messages are fetched and provided as conversation
    | history. Higher values give the agent more context but consume more
    | tokens per request.
    |
    */

    'conversation' => [
        'history_limit' => (int) env('WA_HISTORY_LIMIT', 100),
    ],

];
