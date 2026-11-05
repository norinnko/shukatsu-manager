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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 16)->default('other');
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('location')->nullable();
            $table->string('online_url', 1024)->nullable();
            $table->text('memo')->nullable();
            $table->string('status', 16)->default('upcoming');
            $table->string('source', 16)->default('manual');

            // Google Calendar sync.
            $table->boolean('sync_google')->default(false);
            $table->string('google_event_id')->nullable();
            $table->timestamp('google_synced_at')->nullable();
            $table->string('google_sync_error')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'starts_at']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
