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
        // What the user does not want to see, written by OpenAI from the
        // mails they marked "次から表示しない".
        Schema::create('mail_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('description');
            $table->string('example_subject', 512)->nullable();
            $table->foreignId('source_email_id')->nullable()->constrained('emails')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('emails', function (Blueprint $table) {
            $table->dropColumn('matched_by');

            $table->string('ai_status', 16)->default('pending')->after('status');
            $table->string('ai_category', 16)->nullable()->after('ai_status');
            $table->string('ai_decision', 16)->nullable()->after('ai_category');
            $table->string('ai_summary', 500)->nullable()->after('ai_decision');
            $table->string('ai_reason', 500)->nullable()->after('ai_summary');
            $table->json('ai_events')->nullable()->after('ai_reason');
            $table->string('ai_error')->nullable()->after('ai_events');
            $table->foreignId('mail_rule_id')->nullable()->after('ai_error')->constrained()->nullOnDelete();

            $table->index(['user_id', 'ai_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mail_rule_id');
            $table->dropIndex(['user_id', 'ai_status']);
            $table->dropColumn(['ai_status', 'ai_category', 'ai_decision', 'ai_summary', 'ai_reason', 'ai_events', 'ai_error']);
            $table->string('matched_by', 64)->default('');
        });

        Schema::dropIfExists('mail_rules');
    }
};
