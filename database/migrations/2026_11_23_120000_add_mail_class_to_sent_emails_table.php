<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use jdavidbakr\MailTracker\MailTracker;

class AddMailClassToSentEmailsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $connection = MailTracker::sentEmailModel()->getConnectionName();

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->dropIndex('sent_emails_type_created_at_index');
        });

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->string('mail_class')->nullable()->after('hash');
            $table->index(['mail_class', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $connection = MailTracker::sentEmailModel()->getConnectionName();

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->dropIndex('sent_emails_mail_class_created_at_index');
        });

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->dropColumn('mail_class');
        });

        Schema::connection($connection)->table('sent_emails', function (Blueprint $table) {
            $table->string('type')->default('unknown')->after('hash');
            $table->index(['type', 'created_at']);
        });
    }
}
