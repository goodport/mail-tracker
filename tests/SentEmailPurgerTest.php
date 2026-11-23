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
use jdavidbakr\MailTracker\Tests\Support\MarketingNotification;
use PHPUnit\Framework\Attributes\Test;

class SentEmailPurgerTest extends TestCase
{
    /**
     * Emails are purged according to the retention configured for their type.
     */
    #[Test]
    public function it_purges_emails_by_type_retention(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-type', ['marketing' => 30]);

        $expiredMarketing = $this->createSentEmail('marketing', 45);
        $keptMarketing = $this->createSentEmail('marketing', 10);
        $keptUnknown = $this->createSentEmail(MailTracker::TYPE__UNKNOWN, 45);
        $expiredUnknown = $this->createSentEmail(MailTracker::TYPE__UNKNOWN, 70);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseMissing('sent_emails', ['id' => $expiredMarketing->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expiredUnknown->id]);
        $this->assertDatabaseHas('sent_emails', ['id' => $keptMarketing->id]);
        $this->assertDatabaseHas('sent_emails', ['id' => $keptUnknown->id]);
    }

    /**
     * A type configured to zero days is kept forever.
     */
    #[Test]
    public function it_keeps_a_type_configured_to_zero_days(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-type', ['transactional' => 0]);

        $kept = $this->createSentEmail('transactional', 365);
        $expired = $this->createSentEmail(MailTracker::TYPE__UNKNOWN, 70);

        (new SentEmailPurger)->purge();

        $this->assertDatabaseHas('sent_emails', ['id' => $kept->id]);
        $this->assertDatabaseMissing('sent_emails', ['id' => $expired->id]);
    }

    /**
     * Emails are purged using the retention of their notification type.
     */
    #[Test]
    public function it_purges_emails_by_notification_type(): void
    {
        Config::set('mail-tracker.expire-days', 60);
        Config::set('mail-tracker.expire-days-by-type', ['marketing' => 1]);
        Config::set('mail-tracker.notification-types', [
            MarketingNotification::class => 'marketing',
        ]);

        Notification::route('mail', 'recipient@example.com')
            ->notify(new MarketingNotification);

        $notificationEmail = MailTracker::sentEmailModel()->newQuery()->first();
        $notificationEmail->created_at = Carbon::now()->subDays(10);
        $notificationEmail->save();

        $defaultEmail = $this->createSentEmail(MailTracker::TYPE__UNKNOWN, 10);

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

        $email = $this->createSentEmail(MailTracker::TYPE__UNKNOWN, 10);
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

    private function createSentEmail(string $type, int $ageInDays): Model
    {
        $email = MailTracker::sentEmailModel();
        $email->hash = Str::random(32);
        $email->type = $type;
        $email->created_at = Carbon::now()->subDays($ageInDays);
        $email->save();

        return $email;
    }
}
