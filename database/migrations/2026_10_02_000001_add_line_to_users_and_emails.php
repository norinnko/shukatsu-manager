<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // LINE user to push to (shown in LINE Developers as "あなたのユーザーID").
            $table->string('line_user_id', 64)->nullable()->after('gmail_sync_error');
            // Mails received before this are never pushed, so linking LINE
            // does not flood it with old mail.
            $table->dateTime('line_linked_at')->nullable()->after('line_user_id');
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dateTime('line_notified_at')->nullable()->after('mail_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('line_notified_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['line_user_id', 'line_linked_at']);
        });
    }
};
