# MailTracker

MailTracker hooks into outgoing Laravel email, injects a tracking pixel and
tracked links, stores the rendered message, and counts opens and clicks. It also
provides an optional admin panel for reviewing sent email.

This repository is the `goodport/mail-tracker` fork of
[`jdavidbakr/mail-tracker`](https://github.com/jdavidbakr/MailTracker), originally
created by J David Baker. The PHP namespace remains `jdavidbakr\MailTracker`.

Compared with the upstream package, this fork adds per-type retention for sent
email and replaces the unbounded purge with scheduled batch deletion.

## Requirements

- PHP 8.2 or higher
- Laravel 10, 11, 12, or 13
- A cache store that supports locks (the default Laravel stores do)

## Installation

Install the package through Composer:

```bash
composer require goodport/mail-tracker
```

Publish the configuration file and migrations:

```bash
php artisan vendor:publish --provider="jdavidbakr\MailTracker\MailTrackerServiceProvider"
```

Run the migrations:

```bash
php artisan migrate
```

If you store the package models on a different database connection, set the
`connection` option in `config/mail-tracker.php` before running the migrations.

If you prefer to manage the schema yourself, skip the package migrations:

```php
// In AppServiceProvider::register()
MailTracker::ignoreMigrations();
```

## Configuration

All settings live in `config/mail-tracker.php`.

### Tracking

- **inject-pixel** — inject a tracking pixel into outgoing HTML email.
- **track-links** — rewrite anchor links so clicks are logged before redirecting
  the recipient to the target URL.
- **route** — prefix and middleware for the tracking endpoints.
- **admin-route** — enable or disable the admin routes, and set their prefix and
  middleware.
- **admin-template** — layout and section used by the admin views.
- **emails-per-page** — page size in the admin list.
- **date-format** — date format used in the admin panel.

### Storage

- **log-content** — store the rendered email body.
- **log-content-strategy** — `database` or `filesystem`.
- **tracker-filesystem** — disk used by the `filesystem` strategy. `null` uses
  the default disk.
- **tracker-filesystem-folder** — folder for content files.
- **content-max-size** — maximum number of characters stored in the `content`
  column.
- **connection** — database connection for the package models. `null` uses the
  application default.

### Retention

- **expire-days** — default retention in days for every type without an
  override. Set it to `0` to keep email forever.
- **expire-days-by-type** — per-type overrides in days. A type set to `0` is kept
  forever.
- **mailable-types** — maps a Mailable class to a retention type.
- **notification-types** — maps a Notification class to a retention type. Used
  for notifications sent through the `mail` channel.
- **purge-schedule** — cron expression for the automatic purge. Defaults to
  daily at 03:00 in the application timezone. Set it to `null` to disable the
  automatic schedule and run the command manually.

### Queues and integrations

- **tracker-queue** — queue for tracking jobs. `null` uses the default queue.
- **sns-topic** — when set, Amazon SNS notifications from other topics are
  ignored.
- **search-date-start** — default lower bound, in days, for the admin search.
- **fallback-event-listeners** — listeners for signed tracking links that were
  created before the signature middleware was introduced.

## Per-Type Retention

By default, every email is retained for `expire-days` days. To keep
different kinds of email for different periods, map the sending class to a type
and configure that type. Mailables are mapped with `mailable-types`, and
notifications sent through the `mail` channel are mapped with
`notification-types`.

```php
// config/mail-tracker.php
'expire-days' => 60,

'expire-days-by-type' => [
    'marketing'     => 30,
    'transactional' => 365,
    'receipt'       => 0, // kept forever
    'notification'  => 90,
],

'mailable-types' => [
    App\Mail\MarketingMail::class      => 'marketing',
    App\Mail\ReceiptMail::class        => 'receipt',
    App\Mail\WelcomeMail::class        => 'transactional',
],

'notification-types' => [
    App\Notifications\ExampleNotification::class => 'notification',
],
```

The type is resolved when the message is sent, in this order:

- a Mailable listed in `mailable-types` is stored with the configured type;
- otherwise, a Notification listed in `notification-types` is stored with the
  configured type;
- a Mailable or Notification without a mapping, and messages sent without
  either, are stored as `unknown`.

Queued notifications are resolved the same way; Laravel passes the notification
class to the mail event for both synchronous and queued delivery.

A type missing from `expire-days-by-type` follows `expire-days`,
including `unknown`. Set a type to `0` to keep it forever.

The type is stored in the `type` column of `sent_emails`. The migration defaults
existing rows to `unknown`, so they follow the default retention. The package
does not classify historical rows; if you need to separate old email by type,
that is an application-level task.

### How purging works

Cleanup is handled by the `mail-tracker:purge` command. The package registers it
in the Laravel scheduler, so no application code changes are needed. The default
schedule is daily at 03:00; change `purge-schedule` to any cron expression to run
it more or less often, or set it to `null` to disable automatic registration:

```php
'purge-schedule' => '0 3 * * *', // daily at 03:00
// 'purge-schedule' => '0 * * * *', // hourly
// 'purge-schedule' => null,        // manual runs only
```

The scheduled run:

- expired rows are selected and removed in bounded batches, so a single run does
  not load the whole table into memory;
- the number of batches per retention group and the total work per run are
  limited; a backlog is drained gradually across runs;
- `withoutOverlapping()` prevents parallel runs, and `onOneServer()` prevents
  duplicates on multi-server deployments. The cache store must support locks;
- each batch also deletes the related `sent_emails_url_clicked` rows and the
  content files written by the `filesystem` strategy.

With the built-in limits, one run removes at most 10,000 emails. Increase the
schedule frequency if the sending volume is high enough to outpace that.

The command can also be run manually:

```bash
php artisan mail-tracker:purge
```

The scheduler itself must be running for automatic cleanup to happen. Configure
the standard Laravel cron entry on the server:

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Sending an email does not trigger cleanup anymore.

## Disabling Tracking for a Message

Add the `X-No-Track` header to skip tracking for a specific message. Put any
value into the header; it is removed before the email is sent.

```php
Mail::send('email.example', [], function ($message) {
    // ... other settings
    $message->getHeaders()->addTextHeader('X-No-Track', Str::random(10));
});
```

With a Mailable, return the header from the `headers()` method:

```php
public function headers(): array
{
    return [
        'X-No-Track' => Str::random(10),
    ];
}
```

## Disabling Tracking for a Link

Add the `data-dont-track` attribute to anchor tags that should keep their
original URL. The attribute is removed from the final HTML before sending.

```html
<a href="https://example.com/track-this">Tracked link</a>
<a href="https://example.com/keep-this" data-dont-track>Not tracked</a>
```

## Ignoring Filter and Anti-Virus Requests

Some mail servers open emails or follow links while scanning them. Listen to
`ValidActionEvent` and set `skip` to ignore those requests.

```php
class ValidActionListener
{
    public function handle(ValidActionEvent $event): void
    {
        if (in_array(request()->userAgent(), ['Mozilla/5.0', 'SomeScanner/1.0'])) {
            $event->skip = true;
        }
    }
}
```

Register the listener in your `EventServiceProvider` unless event discovery is
enabled.

## Storing Content in the Filesystem

By default, the rendered email body is stored in the `content` column. For large
volumes you can store it on a disk instead:

```php
'log-content-strategy'      => 'filesystem',
'tracker-filesystem'        => null,
'tracker-filesystem-folder' => 'mail-tracker',
```

`tracker-filesystem` selects the disk; `null` uses the default disk. Purge removes
the stored files together with the matching rows.

## Overriding Models

Replace the default models in `AppServiceProvider`:

```php
MailTracker::useSentEmailModel(App\Models\TrackedEmail::class);
MailTracker::useSentEmailUrlClickedModel(App\Models\TrackedEmailUrlClicked::class);
```

Custom models must implement `SentEmailModel` or `SentEmailUrlClickedModel`.
The package traits provide the shared behavior:

```php
use Illuminate\Database\Eloquent\Model;
use jdavidbakr\MailTracker\Concerns\IsSentEmailModel;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

class TrackedEmail extends Model implements SentEmailModel
{
    use IsSentEmailModel;

    protected static $unguarded = true;

    protected $casts = [
        'meta'       => 'collection',
        'opened_at'  => 'datetime',
        'clicked_at' => 'datetime',
    ];
}
```

A replacement `SentEmail` model must provide the `type` column when retention is
enabled.

## Admin Panel

The admin panel is enabled by default. It is protected by the
`can:see-sent-emails` middleware; define that gate or replace the middleware in
the `admin-route` configuration. The default prefix is `email-manager`, and the
index route is named `mailTracker_Index`. The admin routes can be disabled
completely.

The panel lists sent email with open and click counts, shows individual messages,
and lists clicked URLs. All admin views can be published and customized.

## Events

Tracking updates are applied through queued jobs, so an email blast does not
overwhelm the database. Select the queue with `tracker-queue`.

The package fires these events:

- `EmailSentEvent` — public property `sent_email`.
- `ViewEmailEvent` — public properties `sent_email` and `ip_address`.
- `LinkClickedEvent` — public properties `sent_email`, `ip_address`, and
  `link_url`.

When Amazon SNS notifications are configured, these events are also fired:

- `EmailDeliveredEvent` — public properties `sent_email` and `email_address`.
- `ComplaintMessageEvent` — public properties `sent_email` and `email_address`.
- `PermanentBouncedMessageEvent` — public properties `sent_email` and
  `email_address`.
- `TransientBouncedMessageEvent` — public properties `sent_email`,
  `email_address`, `bounce_sub_type`, and `diagnostic_code`.

To link a tracked email to an application model, add a header to the outgoing
message and read it from the event listener:

```php
Mail::send('email.example', [], function ($message) use ($order) {
    $message->to('recipient@example.com');
    $message->getHeaders()->addTextHeader('X-Order-ID', $order->id);
});
```

```php
public function handle(EmailSentEvent $event): void
{
    $orderId = $event->sent_email->getHeader('X-Order-ID');
    // ...
}
```

Headers are sent with the message, so do not put private data into them.

## Amazon SES

To receive delivery, bounce, and complaint notifications, create an SES
notification topic and subscribe the package callback URL, which is shown on the
admin page. For extra security, set `sns-topic` to the exact topic ARN; events
from other topics are then ignored.

## Exceptions

- `jdavidbakr\MailTracker\Exceptions\BadUrlLink` — a tracking link could not be
  parsed for redirect.

## Development Notes

The tracking pixel requires the recipient's email client to load images from your
server. Development domains that are not reachable from the email client, and
webmail clients that proxy images, may not register opens. A local SMTP capture
tool such as `mailtrap.io` is useful for development.

## Testing

```bash
composer test
```

Run a single test file:

```bash
vendor/bin/phpunit --no-coverage tests/SentEmailPurgerTest.php
```

Tests use Orchestra Testbench with an in-memory SQLite database. Migrations are
applied by the package test case.

## Contributing

See [CONTRIBUTING](CONTRIBUTING.md) and [CONDUCT](CONDUCT.md).

## Security

Please report security issues privately through the repository's security
advisories instead of opening a public issue.

## Credits

- Goodport — current fork maintenance
- J David Baker — original package

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
