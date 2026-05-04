# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
This project has not yet reached a stable release. Breaking changes may occur between any two versions until v1.0.0.

## [Unreleased]

### Added
- `CONTRIBUTING.md`, `SECURITY.md`, `CHANGELOG.md`, `CODE_OF_CONDUCT.md` for open-source community readiness.
- GitHub Actions CI workflows: `tests.yml` (PHPUnit on PHP 8.3 + 8.4) and `lint.yml` (Pint).
- `AGENTS.md` now tracked in version control (was previously gitignored).
- `tests/Feature/RemembersConversationsTest.php` — tests for `RemembersConversations` trait.
- `ProcessWhatsAppMessageTest` now sets up an in-memory SQLite schema so `WhatsAppAgent` (which queries conversation history) works under test.

### Changed
- **Breaking:** `GenericAgent` renamed to `WhatsAppAgent`. Update any reference to `\LaravelWhatsApp\Agents\GenericAgent` in your published `config/whatsapp-agent.php`.
- Conversation history (`RemembersConversations::messages()`) is now returned in chronological order (oldest → newest). Previously messages were newest-first, which reversed turn order in the model's context.
- `RemembersConversations::messages()` now returns a plain `array` instead of an Eloquent `Collection`, matching the type expected by `laravel/ai`'s `FakeTextGateway`.
- `RemembersConversations::continueLastConversation()` parameter changed from `object $as` to `string $senderJid`. The method now looks up the most recent chat *that sender participated in* rather than the most recent message globally.
- Default `WA_POLLING_INTERVAL` raised from `5` to `60` seconds.
- `.gitignore` entries updated: removed `AGENTS.md` / `CLAUDE.md`; added `.claude/` and `.idea/`.

[Unreleased]: https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent/compare/HEAD...HEAD
