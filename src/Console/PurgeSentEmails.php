<?php

namespace jdavidbakr\MailTracker\Console;

use Illuminate\Console\Command;
use jdavidbakr\MailTracker\SentEmailPurger;

class PurgeSentEmails extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mail-tracker:purge';

    /**
     * @var string
     */
    protected $description = 'Delete expired tracked emails according to the retention configuration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        (new SentEmailPurger)->purge();

        $this->info('Expired tracked emails have been purged.');

        return self::SUCCESS;
    }
}
