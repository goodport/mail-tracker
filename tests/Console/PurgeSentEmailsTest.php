<?php

namespace jdavidbakr\MailTracker\Tests\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use jdavidbakr\MailTracker\MailTracker;
use jdavidbakr\MailTracker\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PurgeSentEmailsTest extends TestCase
{
    /**
     * The command deletes emails whose retention period has expired.
     */
    #[Test]
    public function it_purges_expired_emails(): void
    {
        Config::set('mail-tracker.expire-days', 1);

        $expired = $this->createSentEmail(10);

        $this->artisan('mail-tracker:purge')
            ->assertSuccessful();

        $this->assertDatabaseMissing('sent_emails', ['id' => $expired->id]);
    }

    /**
     * Sending an email does not purge records anymore; the schedule owns cleanup.
     */
    #[Test]
    public function it_does_not_purge_while_sending(): void
    {
        Config::set('mail-tracker.expire-days', 1);

        $expired = $this->createSentEmail(10);

        Mail::raw('Example content', function ($message) {
            $message->to('recipient@example.com');
        });

        $this->assertDatabaseHas('sent_emails', ['id' => $expired->id]);
    }

    /**
     * The package registers the purge command in the scheduler daily by default.
     */
    #[Test]
    public function it_registers_the_purge_command_in_the_schedule(): void
    {
        $event = $this->getPurgeScheduleEvent();

        $this->assertNotNull($event);
        $this->assertSame('0 3 * * *', $event->expression);
    }

    /**
     * The schedule frequency comes from the configuration.
     */
    #[Test]
    public function it_uses_the_configured_schedule_expression(): void
    {
        Config::set('mail-tracker.purge-schedule', '*/15 * * * *');

        $event = $this->getPurgeScheduleEvent();

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    /**
     * A null schedule expression disables automatic registration.
     */
    #[Test]
    public function it_does_not_register_the_schedule_when_disabled(): void
    {
        Config::set('mail-tracker.purge-schedule', null);

        $this->assertNull($this->getPurgeScheduleEvent());
    }

    private function getPurgeScheduleEvent(): ?Event
    {
        $schedule = $this->app->make(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, 'mail-tracker:purge')) {
                return $event;
            }
        }

        return null;
    }

    private function createSentEmail(int $ageInDays): Model
    {
        $email = MailTracker::sentEmailModel();
        $email->hash = Str::random(32);
        $email->type = MailTracker::TYPE__UNKNOWN;
        $email->created_at = Carbon::now()->subDays($ageInDays);
        $email->save();

        return $email;
    }
}
