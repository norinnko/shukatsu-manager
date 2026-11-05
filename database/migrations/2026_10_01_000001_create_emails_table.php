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
            // Gmail history cursor: only messages added after it are fetched.
            $table->string('gmail_history_id', 32)->nullable()->after('google_calendar_id');
            $table->timestamp('gmail_synced_at')->nullable()->after('gmail_history_id');
            $table->string('gmail_sync_error')->nullable()->after('gmail_synced_at');
        });

        // Job-hunting mails picked out of Gmail. Other mails are never stored.
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gmail_message_id', 32);
            $table->string('gmail_thread_id', 32)->nullable();
            $table->string('from_address');
            $table->string('from_name')->nullable();
            $table->string('subject', 512);
            $table->text('snippet')->nullable();
            $table->longText('body')->nullable();
            $table->dateTime('received_at');
            $table->string('matched_by', 64);
            $table->string('status', 16)->default('new');
            $table->timestamps();

            $table->unique(['user_id', 'gmail_message_id']);
            $table->index(['user_id', 'status', 'received_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['gmail_history_id', 'gmail_synced_at', 'gmail_sync_error']);
        });
    }
};
