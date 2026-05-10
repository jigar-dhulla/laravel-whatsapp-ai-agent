# Laravel WhatsApp AI Agent

[![Tests](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/tests.yml/badge.svg)](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/tests.yml)
[![Lint](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/lint.yml/badge.svg)](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/lint.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jigar-dhulla/laravel-whatsapp-ai-agent.svg)](https://packagist.org/packages/jigar-dhulla/laravel-whatsapp-ai-agent)
[![License](https://img.shields.io/github/license/jigar-dhulla/laravel-whatsapp-ai-agent.svg)](LICENSE)

An extension to the [Laravel AI SDK](https://laravel.com/docs/ai-sdk) that adds WhatsApp as an agent interface. It polls a [wacli](https://github.com/steipete/wacli) SQLite database for new messages, routes them through your configured agents (any class that implements `Laravel\Ai\Contracts\Agent`), and sends replies back via the wacli binary.

## Intro & Demo

Watch the intro and demo on YouTube: [https://www.youtube.com/watch?v=XNAz-Ry2-co](https://www.youtube.com/watch?v=XNAz-Ry2-co)

## Requirements

- PHP 8.3+
- Laravel 13.0+
- `laravel/ai` ^0.6
- [`wacli`](https://github.com/steipete/wacli) **0.8.1+** installed and authenticated on the host

## Installation

```bash
composer require jigar-dhulla/laravel-whatsapp-ai-agent
```

The package registers itself automatically via Laravel's package auto-discovery.

## Setup

Run the setup command to detect your wacli binary and write paths to `.env`:

```bash
php artisan wa:setup
```

This will:
1. Locate your wacli binary
2. Run `wacli doctor` to detect your store directory
3. Prompt for the wacli SQLite database path
4. Write `WA_WACLI_BINARY`, `WA_WACLI_STORE`, and `WA_WACLI_DATABASE` to `.env`

Then publish the config to customize your agents:

```bash
php artisan vendor:publish --tag=whatsapp-agent-config
```

This publishes `config/whatsapp-agent.php`, which includes a default `WhatsAppAgent` ready to use. Edit the config to add your own agents or customize scopes and triggers.

## Configuration

### `config/whatsapp-agent.php`

```php
return [
    'wacli' => [
        'binary'   => env('WA_WACLI_BINARY', 'wacli'),
        'database' => env('WA_WACLI_DATABASE'),
        'store'    => env('WA_WACLI_STORE'),
    ],

    /*
    | One entry per agent. The agent key must be a class that implements
    | Laravel\Ai\Contracts\Agent (use the Promptable trait for the full SDK
    | feature set). Provider and model are optional runtime overrides; if null,
    | the agent class resolves them via its own #[Provider]/#[Model] attributes
    | or config/ai.php defaults.
    |
    | - triggers: [] matches every message in this agent's scope
    | - chats/groups: at least one entry total — empty scope = inactive agent
    */
    'agents' => [
        [
            'agent'    => \JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent::class,
            'triggers' => [],
            'chats'    => [],
            'groups'   => [],
        ],
    ],

    'polling' => [
        'interval_seconds' => (int) env('WA_POLLING_INTERVAL', 60),
    ],
];
```

**AI provider configuration (API keys, default models) belongs in `config/ai.php`**, managed by `laravel/ai` — not in this package.

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `WA_WACLI_BINARY` | `wacli` | Path to the wacli binary (0.6.0+ required) |
| `WA_WACLI_DATABASE` | — | Path to the wacli SQLite database (set by `wa:setup`) |
| `WA_WACLI_STORE` | — | Path to the wacli store directory (set by `wa:setup`) |
| `WA_AGENT_PROVIDER` | — | Optional: AI provider override for the default agent (e.g. `anthropic`, `openai`) |
| `WA_AGENT_MODEL` | — | Optional: Model identifier override for the default agent |
| `WA_POLLING_INTERVAL` | `60` | Seconds between database polls |
| `WA_HISTORY_LIMIT` | `100` | Max past messages included in agent context |

### Creating Custom Agents

Generate a new agent with `make:agent`:

```bash
php artisan make:agent CustomAgent
```

This creates `app/Ai/Agents/CustomAgent.php`. Then add it to your published `config/whatsapp-agent.php`:

```php
'agents' => [
    [
        'agent'    => \App\Ai\Agents\CustomAgent::class,
        'triggers' => ['@custom'],
        'chats'    => ['111@s.whatsapp.net'],
        'groups'   => [],
    ],
],
```

You can keep the default `WhatsAppAgent` in the config alongside your custom agents, or remove it entirely.

#### Conversation memory

Add the `RemembersWhatsAppConversations` trait to your agent to give it access to the full message history from the wacli SQLite database. The trait implements the Laravel AI SDK's conversation interface, so previous messages in the chat are automatically injected as context on every prompt:

```php
use JigarDhulla\LaravelWhatsApp\Traits\RemembersWhatsAppConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class CustomAgent implements Agent, Conversational
{
    use Promptable;
    use RemembersWhatsAppConversations;
}
```

The number of historical messages included is controlled by `WA_HISTORY_LIMIT` (default `100`). Override `maxConversationMessages()` in your agent class to set a per-agent limit.

### Multiple Agents

One message can match multiple agents simultaneously. Each match dispatches an independent queued job, so agents process in parallel:

```php
// config/whatsapp-agent.php
'agents' => [
    [
        'agent'    => \App\Ai\Agents\SupportAgent::class,
        'triggers' => ['@support'],
        'chats'    => ['111@s.whatsapp.net'],
        'groups'   => ['group1@g.us'],
    ],
    [
        'agent'    => \App\Ai\Agents\SalesAgent::class,
        'triggers' => ['@sales'],
        'chats'    => ['111@s.whatsapp.net'],
        'groups'   => [],
    ],
    [
        'agent'    => \App\Ai\Agents\BroadcastAgent::class,
        'triggers' => [],  // empty = matches all messages in scope
        'chats'    => [],
        'groups'   => ['announcements@g.us'],
    ],
],
```

**Routing rules:**
- A message matches an agent when its chat/group JID is in the agent's `chats` or `groups` list **and** the body contains at least one trigger phrase (case-insensitive).
- An empty `triggers` array matches every message in scope.
- An agent with empty `chats` **and** empty `groups` is inactive and never fires.

## Usage

### Start the wacli sync daemon

```bash
wacli sync --follow --refresh-contacts --refresh-groups
```
You may use a 3rd party tool to make sure this keeps running.

### Start the listener

```bash
php artisan wa:listen
```

Runs an infinite polling loop. Each iteration syncs new wacli messages, routes them via `AgentRouter`, and dispatches one `ProcessWhatsAppMessage` job per matched agent.

```bash
php artisan wa:listen --once          # single iteration, then exit
php artisan wa:listen --max-iterations=10
php artisan wa:listen -vv             # show startup config summary
php artisan wa:listen -vvv            # show each message scanned
```

### Check agent status

```bash
php artisan wa:status
```

Shows wacli auth/connection state and a summary of every configured agent — class name, provider/model, triggers, and scope.

### Queue worker

Each matched message dispatches a `ProcessWhatsAppMessage` job. Run a queue worker alongside the listener:

```bash
php artisan queue:work
```

## How It Works

```
wacli sync  →  SQLite DB  →  WhatsAppMessageReader  →  AgentRouter
                                                              ↓ (one job per match)
                                                  ProcessWhatsAppMessage (queued)
                                                              ↓
                                                      $agent->prompt($body)
                                                              ↓
                                                        wacli send text
```

1. `wa:listen` runs as daemon.
2. `WhatsAppMessageReader` fetches rows newer than the last processed rowid, filtered to the union of all agents' JIDs.
3. `AgentRouter::match($chatJid, $body)` returns every agent config entry whose scope contains the chat and whose triggers match the message.
4. One `ProcessWhatsAppMessage` job is dispatched per matched entry.
5. The job resolves the agent class from the container, calls `$agent->prompt($body)`, and sends the reply via `wacli send text`.

## Testing

```bash
composer install
vendor/bin/phpunit
vendor/bin/pint
```

## License

MIT — see [LICENSE](LICENSE).
