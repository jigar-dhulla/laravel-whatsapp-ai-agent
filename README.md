# Laravel WhatsApp AI Agent

[![Tests](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/tests.yml/badge.svg)](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/tests.yml)
[![Lint](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/lint.yml/badge.svg)](https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/actions/workflows/lint.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/jigar-dhulla/laravel-whatsapp-ai-agent.svg)](https://packagist.org/packages/jigar-dhulla/laravel-whatsapp-ai-agent)
[![License](https://img.shields.io/github/license/jigar-dhulla/laravel-whatsapp-ai-agent.svg)](LICENSE)

Talk to your [Laravel AI SDK](https://laravel.com/docs/ai-sdk) agents over WhatsApp, using [wacli](https://github.com/steipete/wacli) to connect to your account.

▶️ [Watch the intro & demo](https://www.youtube.com/watch?v=XNAz-Ry2-co)

## Requirements

- PHP 8.3+ and Laravel 13+
- [`laravel/ai`](https://laravel.com/docs/ai-sdk) with an AI provider configured in `config/ai.php`
- [`wacli`](https://github.com/steipete/wacli) **0.8.1+**, installed and logged in to WhatsApp

## Getting Started

**1. Install the package**

```bash
composer require jigar-dhulla/laravel-whatsapp-ai-agent
```

**2. Point it at wacli.** This finds your wacli binary and database and writes the paths to `.env`:

```bash
php artisan wa:setup
```

**3. Publish the config**

```bash
php artisan vendor:publish --tag=whatsapp-agent-config
```

**4. Choose which chats the agent answers.** List your chat and group IDs (JIDs):

```bash
php artisan wa:chats     # direct messages
php artisan wa:groups    # groups
```

Add them to the agent in `config/whatsapp-agent.php`. An agent with no chats and no groups stays inactive.

```php
'agents' => [
    [
        'agent'    => \JigarDhulla\LaravelWhatsApp\Agents\WhatsAppAgent::class,
        'triggers' => [],                         // [] = reply to every message
        'chats'    => ['15551234567@s.whatsapp.net'],
        'groups'   => [],
    ],
],
```

**5. Run it.** Start these three processes and keep them running:

```bash
wacli sync --follow --refresh-contacts --refresh-groups   # sync WhatsApp → local DB
php artisan wa:listen                                      # route new messages to agents
php artisan queue:work                                     # run agents and send replies
```

Now send a message from one of the chats you configured. Your agent will reply.

> Stuck? Run `php artisan wa:status` to check that wacli is authenticated and connected, and to see how your agents are configured.

## Configuration

### Agent options

Each entry in `agents` supports:

| Key | Description |
|---|---|
| `agent` | Any class implementing `Laravel\Ai\Contracts\Agent` |
| `triggers` | Phrases that activate the agent (case-insensitive). `[]` matches every message |
| `chats` | Direct-message JIDs the agent listens to |
| `groups` | Group JIDs the agent listens to |
| `mention_sender` | `true` makes group replies @-mention the sender, so they get a notification. Default `false` |

A message goes to **every** agent whose `chats`/`groups` include that chat **and** whose `triggers` appear in the message. Replying to one of the agent's own messages also triggers it, even without a trigger phrase.

The provider, model and API keys come from `config/ai.php` or from the agent class. This package doesn't set them.

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `WA_WACLI_BINARY` | `wacli` | Path to the wacli binary (set by `wa:setup`) |
| `WA_WACLI_DATABASE` | — | Path to the wacli SQLite database (set by `wa:setup`) |
| `WA_WACLI_STORE` | — | Path to the wacli store directory (set by `wa:setup`) |
| `WA_POLLING_INTERVAL` | `1` | Seconds between checks for new messages |
| `WA_HISTORY_LIMIT` | `100` | How many past messages are sent to the agent as context |

## Examples

### A custom agent

```bash
php artisan make:agent SupportAgent
```

```php
// app/Ai/Agents/SupportAgent.php
namespace App\Ai\Agents;

use JigarDhulla\LaravelWhatsApp\Traits\RemembersWhatsAppConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;

class SupportAgent implements Agent, Conversational
{
    use Promptable, RemembersWhatsAppConversations; // the trait gives the agent the chat history

    public function instructions(): string
    {
        return 'You are a friendly support agent for Acme Inc. Keep replies short.';
    }
}
```

```php
// config/whatsapp-agent.php
'agents' => [
    [
        'agent'    => \App\Ai\Agents\SupportAgent::class,
        'triggers' => ['@support'],
        'chats'    => [],
        'groups'   => ['120363000000000000@g.us'],
        'mention_sender' => true,
    ],
],
```

### Several agents in one group

Each agent answers only to its own trigger:

```php
'agents' => [
    ['agent' => \App\Ai\Agents\SupportAgent::class, 'triggers' => ['@support'], 'chats' => [], 'groups' => ['team@g.us']],
    ['agent' => \App\Ai\Agents\SalesAgent::class,   'triggers' => ['@sales'],   'chats' => [], 'groups' => ['team@g.us']],
],
```

### Answer when your number is @-mentioned

Run `php artisan wa:status` to find your linked JID. Then use your number as the trigger:

```php
'triggers' => ['@15551234567'],
```

## Commands

| Command | Description |
|---|---|
| `wa:setup` | Find wacli and write its paths to `.env` |
| `wa:status` | Show wacli connection status and your configured agents |
| `wa:chats` / `wa:groups` | List chat and group JIDs to put in the config |
| `wa:listen` | Start the listener. Add `--once` to run it a single time, or `-vv` to see debug output |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Run the tests with `composer test` and fix code style with `composer format`.

## License

MIT. See [LICENSE](LICENSE).
