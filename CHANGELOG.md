# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- wa:status shows more information

### Changed
- This package now supports wacli 0.8.1+ 

### Removed
- `Wacli` service methods: `syncOnceExitIfIdleForFiveSeconds` and `waitUntilUnlocked`.
- `ProcessWhatsAppMessage` job no longer waits for wacli lock before sending.

### Fixed
- Update last processed id irrespective of the match so the listener advances.

## [0.0.1]

### Added
- `wa:listen` artisan command — infinite polling loop that syncs new wacli messages, routes them through configured agents, and dispatches one queued job per match.
- `wa:setup` artisan command — detects the wacli binary, reads `wacli doctor`, and writes `WA_WACLI_BINARY`, `WA_WACLI_STORE`, and `WA_WACLI_DATABASE` to `.env`.
- `wa:status` artisan command — shows wacli auth/connection state and a summary of every configured agent.
- `WhatsAppAgent` — default agent class implementing the Laravel AI SDK `Agent` contract with WhatsApp conversation memory.
- `RemembersWhatsAppConversations` trait — add to any custom agent to inject recent chat history as context on every prompt. History depth is controlled by `WA_HISTORY_LIMIT` (default `100`); override `maxConversationMessages()` per agent for a custom limit.
- `AgentRouter` — routes incoming messages to agents based on per-agent `chats`, `groups`, and `triggers` config. An empty `triggers` array matches every message in scope; an agent with no `chats` and no `groups` is inactive.
- `ProcessWhatsAppMessage` queued job — resolves the agent from the container, calls `$agent->prompt($body)`, and sends the reply via `wacli send text`.
- `Wacli` service — wraps the wacli binary: `doctor`, `chats`, `groups`, and `send`. All commands automatically include `--store <dir>` when `WA_WACLI_STORE` is set.
- `WacliException` — exception for wacli related errors.
- `WhatsAppMessageReader` — reads new inbound messages from the wacli SQLite database, filtered by the union of all agents' JIDs and the last processed rowid.
- `whatsapp-agent.php` config file (publishable via `--tag=whatsapp-agent-config`) with `wacli`, `agents`, `polling`, and `conversation` sections.
- Environment variables: `WA_WACLI_BINARY`, `WA_WACLI_DATABASE`, `WA_WACLI_STORE`, `WA_POLLING_INTERVAL`, `WA_HISTORY_LIMIT`.
- GitHub Actions CI workflows for tests (PHP 8.3 + 8.4) and code style (Pint).
- `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md` for open-source community readiness.
