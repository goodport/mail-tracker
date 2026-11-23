<?php

namespace jdavidbakr\MailTracker\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\SentEmailPurger;
use jdavidbakr\MailTracker\Tests\Support\MarketingMail;
use jdavidbakr\MailTracker\Tests\Support\MarketingNotification;
use PHPUnit\Framework\Attributes\Test;

class SentEmailPurgerTest extends TestCase
{
    /**
     * Emails are purged according to the retention configured for their class.
     */
    #[Test]
    public function it_purges_emails_by_class_retention(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-class', [
            30 => [
                MarketingMail::class,
                MarketingNotification::class,
            ],
        ]);

        $expiredMailable = $this->createSentEmail(MarketingMail::class, 45);
        $keptMailable = $this->createSentEmail(MarketingMail::class, 10);
        $expiredNotification = $this->createSentEmail(MarketingNotification::class, 45);
        $keptUnmapped = $this->createSentEmail('App\\Mail\\UnmappedExampleMail', 45);
        $expiredUnmapped = $this->createSentEmail('App\\Mail\\UnmappedExampleMail', 70);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseMissing('sent_emails', ['id' => $expiredMailable->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expiredNotification->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expiredUnmapped->id]);
        $this->assertDatabaseHas('sent_emails', ['id' => $keptMailable->id]);
        $this->assertDatabaseHas('sent_emails', ['id' => $keptUnmapped->id]);
    }

    /**
     * A class configured to zero days is kept forever.
     */
    #[Test]
    public function it_keeps_a_class_configured_to_zero_days(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-class', [
            0 => [
                MarketingMail::class,
            ],
        ]);

        $kept = $this->createSentEmail(MarketingMail::class, 365);
        $expired = $this->createSentEmail('App\\Mail\\UnmappedExampleMail', 70);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseHas('sent_emails', ['id' => $kept->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expired->id]);
    }

    /**
     * Emails without a class follow the default retention.
     */
    #[Test]
    public function it_applies_the_default_retention_to_emails_without_a_class(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-class', [
            30 => [
                MarketingMail::class,
            ],
        ]);

        $kept = $this->createSentEmail(null, 45);
        $expired = $this->createSentEmail(null, 70);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseHas('sent_emails', ['id' => $kept->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expired->id]);
    }

    /**
     * Emails are purged using the retention of their notification class.
     */
    #[Test]
    public function it_purges_emails_by_notification_class(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-class', [
            1 => [
                MarketingNotification::class,
            ],
        ]);

        Notification::route('mail', 'recipient@example.com')
            ->notify(new MarketingNotification);

        $notificationEmail = MailTracker::sentEmailModel()->newQuery()->first();
        $notificationEmail->created_at = Carbon::now()->subDays(10);
        $notificationEmail->save();

        $defaultEmail = $this->createSentEmail(MarketingMail::class, 10);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseMissing('sent_emails', ['id' => $notificationEmail->id]);
        $this->assertDatabaseHas('sent_emails', ['id' => $defaultEmail->id]);
    }

    /**
     * Purging removes related url clicks and filesystem content files.
     */
    #[Test]
    public function it_removes_url_clicks_and_content_files(): void
    {
        Config::set('mail-tracker.expire-days', 1);
        Config::set('mail-tracker.tracker-filesystem', 'local');
        Config::set('mail-tracker.tracker-filesystem-folder', 'mail-tracker');

        Storage::fake('local');

        $email = $this->createSentEmail(MarketingMail::class, 10);
        $email->meta = collect(['content_file_path' => 'mail-tracker/example.html']);
        $email->save();

        Storage::disk('local')->put('mail-tracker/example.html', 'content');

        $click = MailTracker::sentEmailUrlClickedModel()->newQuery()->create([
            'sent_email_id' => $email->id,
            'hash' => Str::random(32),
        ]);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseMissing('sent_emails', ['id' => $email->id]);
        $this->assertDatabaseMissing('sent_emails_url_clicked', ['id' => $click->id]);
        Storage::disk('local')->assertMissing('mail-tracker/example.html');
    }

    private function createSentEmail(?string $mailClass, int $ageInDays): Model
    {
        $email = MailTracker::sentEmailModel();
        $email->hash = Str::random(32);
        $email->mail_class = $mailClass;
        $email->created_at = Carbon::now()->subDays($ageInDays);
        $email->save();

        return $email;
    }
}
