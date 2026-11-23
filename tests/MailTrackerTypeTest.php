<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use jdavidbakr\MailTracker\MailTracker;
use PHPUnit\Framework\Attributes\Test;

class MarketingMail extends Mailable
{
    public function build()
    {
        return $this->markdown('email.markdown');
    }
}

class NotificationMail extends Mailable
{
    public function build()
    {
        return $this->markdown('email.markdown');
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
        Mail::to('recipient@example.com')->send(new NotificationMail);

        $this->assertDatabaseHas('sent_emails', [
            'type' => MailTracker::TYPE__UNKNOWN,
        ]);
    }

    /**
     * Raw mail sent without a mailable is stored as unknown.
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
}
