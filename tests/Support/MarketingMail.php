<?php

namespace jdavidbakr\MailTracker\Tests\Support;

use Illuminate\Mail\Mailable;

class MarketingMail extends Mailable
{
    public function build()
    {
        return $this->markdown('email.markdown');
    }
}
