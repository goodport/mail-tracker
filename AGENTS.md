# AGENTS.md

## About the Package

`goodport/mail-tracker` logs and tracks outgoing email from a Laravel application.
It injects a tracking pixel and tracked links into outgoing HTML, stores the
rendered message, counts opens and clicks, and exposes an optional admin panel.

The package is a fork of `jdavidbakr/mail-tracker`. The PHP namespace remains
`jdavidbakr\MailTracker`; only the Composer package name changed.

## Stack

- PHP 8.2+
- Laravel 10, 11, 12, or 13
- Testing: Orchestra Testbench and PHPUnit
- Formatting: Laravel Pint

## Structure

- `src/MailTracker.php` — mailer event hook, tracker creation, link and pixel
  injection, message id resolution, and the type resolver.
- `src/SentEmailPurger.php` — per-type retention, batched deletion, related
  click rows, and filesystem content files.
- `src/Console/PurgeSentEmails.php` — `mail-tracker:purge` command.
- `src/MailTrackerServiceProvider.php` — config and view publishing, event
  listeners, command and scheduler registration, and routes.
- `src/MailTrackerController.php`, `src/AdminController.php`,
  `src/SNSController.php` — tracking endpoints, admin panel, and SNS callbacks.
- `src/Model/` — default `SentEmail` and `SentEmailUrlClicked` models.
- `src/Contracts/` — interfaces for replacing the default models.
- `src/Concerns/` — reusable model traits, including content storage and
  deletion hooks.
- `src/Events/`, `src/Record*Job.php` — public events and queued tracking jobs.
- `config/mail-tracker.php` — publishable package configuration.
- `database/migrations/` — `sent_emails` and `sent_emails_url_clicked` schema.
- `tests/` — Testbench feature tests, command tests, and purge unit tests.

## Key Behavior

- Every outgoing message is inspected on `MessageSending`. Unless the message
  carries the `X-No-Track` header, one `sent_emails` row is stored per recipient
  and sender pair.
- The retention type is resolved from `$event->data['__laravel_mailable']`
  through `mailable-types`. Mailables without a mapping, and messages sent
  without a mailable, are stored with the `unknown` type.
- `expire-days` defines the retention for every type without an override.
  `expire-days-by-type` overrides it per type. A value of `0` keeps the matching
  emails forever.
- Existing rows receive the `unknown` type from the migration default. The
  package does not classify historical rows; that is an application concern.
- Cleanup is owned by the `mail-tracker:purge` command, which the service
  provider registers in the Laravel scheduler. `purge-schedule` holds the cron
  expression and defaults to daily at 03:00; `null` disables automatic
  registration. Sending an email no longer purges records. The application must
  run `php artisan schedule:run`.
- The command selects expired rows per retention group, removes at most a fixed
  number of batches per group, and stops after a fixed number of batches per run.
- `withoutOverlapping()` and `onOneServer()` guard the scheduled command. The
  configured cache store must support locks.
- Each purge batch removes the related `sent_emails_url_clicked` rows and the
  content files stored by the filesystem strategy before deleting the emails.
  Bulk deletes bypass model events, so files are removed explicitly.
- Content is stored in the database by default. With the `filesystem` strategy,
  content is written to the configured disk and folder, and the path is kept in
  the `meta` column.
- The default `SentEmail` model uses the `type` column; replacement models must
  provide the same column when retention is enabled.

## Testing

Run the full suite:

```bash
composer test
```

Run a targeted test file:

```bash
vendor/bin/phpunit --no-coverage tests/SentEmailPurgerTest.php
```

Tests use Orchestra Testbench with an in-memory SQLite database. Never reset a
database with `migrate:fresh`; migrations run through the package test case.

## Documentation and Language

All code comments, PHPDoc, README, AGENTS, CHANGELOG, and examples are written in
English because the package is published publicly. Examples must use neutral,
fictional data and must not reference any private application, domain, or
internal infrastructure. Keep `CHANGELOG.md` up to date.
