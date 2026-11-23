<?php

return [
    /**
     * To disable the pixel injection, set this to false.
     */
    'inject-pixel'              => true,

    /**
     * To disable injecting tracking links, set this to false.
     */
    'track-links'               => true,

    /**
     * Default number of days to keep sent emails.
     * Used for every class not listed in "expire-days-by-class".
     * Set to 0 to keep them forever.
     */
    'expire-days'               => 60,

    /**
     * Retention overrides grouped by number of days.
     * Each group is a list of Mailable or Notification classes.
     * Classes not listed here use "expire-days".
     * Set a group key to 0 to keep its classes forever.
     */
    'expire-days-by-class'      => [
        // 365 => [
        //     App\Mail\ExampleMail::class,
        //     App\Notifications\ExampleNotification::class,
        // ],
    ],

    /**
     * Cron expression for the scheduled retention purge.
     * Defaults to daily at 03:00 in the application timezone.
     * Set to null to register no schedule and run the command manually.
     */
    'purge-schedule'            => '0 3 * * *',

    /**
     * Where should the pingback URL route be?
     */
    'route'                     => [
        'prefix'     => 'email',
        'middleware' => ['api'],
    ],

    /**
     * If we get a link click without a URL, where should we send it to?
     */
    'redirect-missing-links-to' => '/',

    /**
     * Where should the admin route be?
     */
    'admin-route'               => [
        'enabled'    => true, // Should the admin routes be enabled?
        'prefix'     => 'email-manager',
        'middleware' => [
            'web',
            'can:see-sent-emails',
        ],
    ],

    /**
     * Admin Template
     * example
     * 'name' => 'layouts.app' for Default emailTraking use 'emailTrakingViews::layouts.app'
     * 'section' => 'content' for Default emailTraking use 'content'
     * 'styles_section' => 'styles' for Default emailTraking use 'styles'
     */
    'admin-template'            => [
        'name'    => 'emailTrakingViews::layouts.app',
        'section' => 'content',
    ],

    /**
     * Number of emails per page in the admin view
     */
    'emails-per-page'           => 30,

    /**
     * Date Format
     */
    'date-format'               => 'm/d/Y g:i a',

    /**
     * Default database connection name (optional - use null for default)
     */
    'connection'                => null,

    /**
     * The SNS notification topic - if set, discard all notifications not in this topic.
     */
    'sns-topic'                 => null,

    /**
     * Determines whether the body of the email is logged in the sent_emails table
     */
    'log-content'               => true,

    /**
     * Determines whether the body should be stored in a file instead of database
     * Can be either 'database' or 'filesystem'
     */
    'log-content-strategy'      => 'database',

    /**
     * What filesystem we use for storing content html files
     */
    'tracker-filesystem'        => null,
    'tracker-filesystem-folder' => 'mail-tracker',

    /**
     * What queue should we dispatch our tracking jobs to?  Null will use the default queue.
     */
    'tracker-queue'             => null,

    /**
     * Size limit for content length stored in database
     */
    'content-max-size'          => 65535,

    /**
     * Length of time to default past email search - if set, will set the default past limit to the amount of days below (Ex: => 356)
     */
    'search-date-start'         => null,

    /**
     * Fallback method for when ValidateSignature has been introduced, but old links still need to be supported
     */
    'fallback-event-listeners' => [
        \jdavidbakr\MailTracker\Listener\DomainExistsInContentListener::class,
    ]
];
