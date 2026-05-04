# Contributing

Thank you for considering a contribution to this package.

## Requirements

- PHP 8.3+
- Composer

## Setup

```bash
git clone https://github.com/jigar-dhulla/laravel-whatsapp-ai-agent.git
cd laravel-whatsapp-ai-agent
composer install
```

## Running Tests

```bash
vendor/bin/phpunit
```

## Code Style

This project uses [Laravel Pint](https://laravel.com/docs/pint) (PSR-12 preset). Before submitting a PR, run:

```bash
vendor/bin/pint
```

The CI lint workflow will fail if the code does not pass `vendor/bin/pint --test`.

## Pull Requests

1. Fork the repository and create a branch from `main`.
2. Write or update tests for any changed behaviour — the CI must stay green.
3. Run `vendor/bin/pint` to fix code style before pushing.
4. Open a pull request against `main` with a clear title and description of what changed and why.

## Reporting Issues

Use the GitHub issue tracker. For security vulnerabilities, see [SECURITY.md](SECURITY.md).

## Commit Messages

Use the imperative mood in the subject line ("Fix bug" not "Fixed bug"), keep the first line under 72 characters, and add a blank line before the body when additional context is useful.
