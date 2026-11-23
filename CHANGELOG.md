# Changelog

All notable changes to this package are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-11-23

### Added

- `notification-types` configuration key that maps a Notification class to a
  retention type.
- Type resolution now checks `__laravel_notification` after
  `__laravel_mailable`, so notifications sent through the `mail` channel use
  their own retention. Queued notifications resolve the same way.
- The admin email list shows the stored type, and the email view page displays
  it above the rendered content.

### Changed

- Notification email is no longer stored as `unknown` when its class is mapped
  in `notification-types`.

## [1.1.0] - 2026-11-23

### Added

- `purge-schedule` configuration key with a cron expression. It defaults to
  daily at 03:00 in the application timezone, and `null` disables automatic
  registration so the command can be scheduled manually.

### Changed

- The scheduled purge now follows `purge-schedule` instead of a fixed hourly
  cadence.

## [1.0.0] - 2026-11-23

This is the first release of the `goodport/mail-tracker` fork. It is based on
`jdavidbakr/mail-tracker`; the PHP namespace remains `jdavidbakr\MailTracker`.

### Added

- Per-type retention: `expire-days-by-type` overrides the default retention for
  selected email types, and a type set to `0` is kept forever.
- `type` column on the `sent_emails` table with a composite
  `(type, created_at)` index.
- Automatic type resolution from the mailable class via `mailable-types`.
  Mailables without a configured type are stored as `unknown`.
- `mail-tracker:purge` command, registered in the scheduler to run hourly.
  The command deletes expired email in bounded batches.
- Overlap protection through `withoutOverlapping()` and `onOneServer()`.
- `AGENTS.md` with the package architecture and behavior overview.

### Changed

- The Composer package name is now `goodport/mail-tracker`.
- Cleanup moved from the email sending path to the scheduled
  `mail-tracker:purge` command. Sending an email no longer deletes records.
- `expire-days` remains the default retention period and now also acts as the
  fallback for every type without an override.
- The purge resolves retention per email type instead of applying one global
  cutoff.
- Content files stored by the filesystem strategy are removed together with the
  related database rows in the same batch.

### Upgrade Notes

- Add `expire-days-by-type` and `mailable-types` to the published
  `config/mail-tracker.php` when per-type retention is needed. Existing
  `expire-days` values keep working unchanged.
- Run the migrations to add the `type` column. Existing rows receive the
  `unknown` type and follow the default retention. Classification of historical
  rows is intentionally left to the application.
- Ensure the application runs the Laravel scheduler (`php artisan schedule:run`
  from cron), because `mail-tracker:purge` is now registered there.
