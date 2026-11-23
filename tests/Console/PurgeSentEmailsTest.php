<?php

namespace jdavidbakr\MailTracker\Tests\Console;

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
     * The package registers the purge command in the scheduler.
     */
    #[Test]
    public function it_registers_the_purge_command_in_the_schedule(): void
    {
        $schedule = $this->app->make(Schedule::class);

        $events = array_filter(
            $schedule->events(),
            fn ($event) => str_contains((string) $event->command, 'mail-tracker:purge')
        );

        $this->assertCount(1, $events);
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
