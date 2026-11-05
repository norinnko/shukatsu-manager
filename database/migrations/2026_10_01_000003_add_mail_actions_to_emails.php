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
        Schema::table('emails', function (Blueprint $table) {
            // What the mail asks to do with the calendar: add a confirmed
            // event, move or cancel an existing one. Applied only by a click.
            $table->renameColumn('ai_events', 'ai_actions');

            // Sign-up page of an event the user has not joined yet.
            $table->string('ai_signup_url', 1024)->nullable()->after('ai_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('ai_signup_url');
            $table->renameColumn('ai_actions', 'ai_events');
        });
    }
};
