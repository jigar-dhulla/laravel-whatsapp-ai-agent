<?php

declare(strict_types=1);
use LaravelWhatsApp\Agents\WhatsAppAgent;

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
    | - agent:    Laravel AI SDK Agent Class.
    | - provider: Optional Lab enum string override (e.g. 'anthropic', 'openai').
    |             Null defers to the agent class's #[Provider] attribute.
    | - model:    Optional model identifier override. Null defers to #[Model].
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
            'provider' => env('WA_AGENT_PROVIDER'),
            'model' => env('WA_AGENT_MODEL'),
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
        'interval_seconds' => (int) env('WA_POLLING_INTERVAL', 60),
    ],

];
