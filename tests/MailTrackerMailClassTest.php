<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\Tests\Support\MarketingMail;
use jdavidbakr\MailTracker\Tests\Support\MarketingNotification;
use jdavidbakr\MailTracker\Tests\Support\QueuedMarketingNotification;
use PHPUnit\Framework\Attributes\Test;

class MailClassInspectingMailTracker extends MailTracker
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveMailClassFor(array $data): ?string
    {
        return $this->resolveMailClass($data);
    }
}

class MailTrackerMailClassTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__);
    }

    /**
     * A Mailable message stores its class for retention lookup.
     */
    #[Test]
    public function it_stores_the_mailable_class(): void
    {
        Mail::to('recipient@example.com')->send(new MarketingMail);

        $this->assertDatabaseHas('sent_emails', [
            'mail_class' => MarketingMail::class,
        ]);
    }

    /**
     * A notification sent through the mail channel stores its class.
     */
    #[Test]
    public function it_stores_the_notification_class(): void
    {
        Notification::route('mail', 'recipient@example.com')
            ->notify(new MarketingNotification);

        $this->assertDatabaseHas('sent_emails', [
            'mail_class' => MarketingNotification::class,
        ]);
    }

    /**
     * A queued notification stores the same notification class.
     */
    #[Test]
    public function it_stores_the_queued_notification_class(): void
    {
        Config::set('queue.default', 'sync');

        Notification::route('mail', 'recipient@example.com')
            ->notify(new QueuedMarketingNotification);

        $this->assertDatabaseHas('sent_emails', [
            'mail_class' => QueuedMarketingNotification::class,
        ]);
    }

    /**
     * Raw mail without a Mailable or Notification stores no class.
     */
    #[Test]
    public function it_stores_no_class_for_raw_mail(): void
    {
        Mail::raw('Example content', function ($message) {
            $message->to('recipient@example.com');
        });

        $this->assertDatabaseHas('sent_emails', [
            'mail_class' => null,
        ]);
    }

    /**
     * A Mailable class wins when both class keys are present.
     */
    #[Test]
    public function it_prefers_the_mailable_class_when_both_keys_are_present(): void
    {
        $mailClass = (new MailClassInspectingMailTracker)->resolveMailClassFor([
            '__laravel_mailable' => MarketingMail::class,
            '__laravel_notification' => MarketingNotification::class,
        ]);

        $this->assertSame(MarketingMail::class, $mailClass);
    }

    /**
     * The notification class is used when there is no Mailable class.
     */
    #[Test]
    public function it_falls_back_to_the_notification_class(): void
    {
        $tracker = new MailClassInspectingMailTracker;

        $this->assertSame(MarketingNotification::class, $tracker->resolveMailClassFor([
            '__laravel_notification' => MarketingNotification::class,
        ]));
        $this->assertNull($tracker->resolveMailClassFor([]));
    }
}
