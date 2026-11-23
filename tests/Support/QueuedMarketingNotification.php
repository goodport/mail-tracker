<?php

namespace jdavidbakr\MailTracker\Tests\Support;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedMarketingNotification extends MarketingNotification implements ShouldQueue {}
