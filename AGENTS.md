# OpenCode Instructions: Laravel WhatsApp AI Agent

## Architecture & Entrypoints
- **Type**: Laravel package (library), not a standalone Laravel application. Do not look for `routes/` or typical app structures.
- **Entrypoints**:
  - `src/WhatsAppAgentServiceProvider.php` (Package registration)
  - `src/Console/Commands/ListenCommand.php` (The main polling loop executed via `php artisan wa:listen`)
- **External Dependencies**: The package integrates with a local `wacli` binary and its SQLite database. 
- **Database Quirks**: It bypasses standard Eloquent connections by dynamically binding the `whatsapp_agent` connection to the `wacli` SQLite file at runtime. `src/Models/WhatsAppMessage.php` expects this external database file to exist.

## Testing & Verification
- **Test Command**: `vendor/bin/phpunit` (Requires `composer install` first). Uses `orchestra/testbench` since this is a package.
- **Linting Command**: `vendor/bin/pint`
- **Note**: `composer.json` does not define shortcut scripts for these; invoke the vendor binaries directly.

## Quirks & Constraints
- `ListenCommand` runs infinitely (`while` loop), polling the `wacli` database and checking cache state. When testing or debugging the listener, always pass `--once` or `--max-iterations=` to prevent long-running process hangs.
- Ensure any tests interacting with `WhatsAppMessageReader` handle the fact that it throws exceptions if the `wacli` database file is missing.
