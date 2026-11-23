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

class TypeInspectingMailTracker extends MailTracker
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function resolveTypeFor(array $data): string
    {
        return $this->resolveType($data);
    }
}

class MailTrackerTypeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::addLocation(__DIR__);
    }

    /**
     * A mapped mailable is stored with its configured retention type.
     */
    #[Test]
    public function it_stores_the_configured_type_for_a_mapped_mailable(): void
    {
        Config::set('mail-tracker.mailable-types', [
            MarketingMail::class => 'marketing',
        ]);

        Mail::to('recipient@example.com')->send(new MarketingMail);

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'marketing',
        ]);
    }

    /**
     * A mailable without a configured type is stored as unknown.
     */
    #[Test]
    public function it_stores_the_unknown_type_for_an_unmapped_mailable(): void
    {
        Mail::to('recipient@example.com')->send(new MarketingMail);

        $this->assertDatabaseHas('sent_emails', [
            'type' => MailTracker::TYPE__UNKNOWN,
        ]);
    }

    /**
     * Raw mail sent without a mailable or notification is stored as unknown.
     */
    #[Test]
    public function it_stores_the_unknown_type_for_raw_mail(): void
    {
        Mail::raw('Example content', function ($message) {
            $message->to('recipient@example.com');
        });

        $this->assertDatabaseHas('sent_emails', [
            'type' => MailTracker::TYPE__UNKNOWN,
        ]);
    }

    /**
     * A mapped notification is stored with its configured retention type.
     */
    #[Test]
    public function it_stores_the_configured_type_for_a_mapped_notification(): void
    {
        Config::set('mail-tracker.notification-types', [
            MarketingNotification::class => 'marketing',
        ]);

        Notification::route('mail', 'recipient@example.com')
            ->notify(new MarketingNotification);

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'marketing',
        ]);
    }

    /**
     * A queued notification keeps its configured type.
     */
    #[Test]
    public function it_stores_the_configured_type_for_a_queued_notification(): void
    {
        Config::set('queue.default', 'sync');
        Config::set('mail-tracker.notification-types', [
            QueuedMarketingNotification::class => 'marketing',
        ]);

        Notification::route('mail', 'recipient@example.com')
            ->notify(new QueuedMarketingNotification);

        $this->assertDatabaseHas('sent_emails', [
            'type' => 'marketing',
        ]);
    }

    /**
     * A mailable mapping wins when both class keys are present.
     */
    #[Test]
    public function it_prefers_the_mailable_type_when_both_keys_are_present(): void
    {
        Config::set('mail-tracker.mailable-types', [
            MarketingMail::class => 'mailable-type',
        ]);
        Config::set('mail-tracker.notification-types', [
            MarketingNotification::class => 'notification-type',
        ]);

        $type = (new TypeInspectingMailTracker)->resolveTypeFor([
            '__laravel_mailable' => MarketingMail::class,
            '__laravel_notification' => MarketingNotification::class,
        ]);

        $this->assertSame('mailable-type', $type);
    }

    /**
     * The notification mapping is used when there is no mailable mapping.
     */
    #[Test]
    public function it_falls_back_to_the_notification_type(): void
    {
        Config::set('mail-tracker.notification-types', [
            MarketingNotification::class => 'notification-type',
        ]);

        $type = (new TypeInspectingMailTracker)->resolveTypeFor([
            '__laravel_notification' => MarketingNotification::class,
        ]);

        $this->assertSame('notification-type', $type);
    }

    /**
     * Unmapped classes and empty data resolve to unknown.
     */
    #[Test]
    public function it_falls_back_to_unknown_without_a_mapping(): void
    {
        $tracker = new TypeInspectingMailTracker;

        $this->assertSame(MailTracker::TYPE__UNKNOWN, $tracker->resolveTypeFor([]));
        $this->assertSame(MailTracker::TYPE__UNKNOWN, $tracker->resolveTypeFor([
            '__laravel_mailable' => MarketingMail::class,
        ]));
        $this->assertSame(MailTracker::TYPE__UNKNOWN, $tracker->resolveTypeFor([
            '__laravel_notification' => MarketingNotification::class,
        ]));
    }
}
