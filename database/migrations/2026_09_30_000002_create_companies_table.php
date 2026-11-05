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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('industry')->nullable();
            $table->string('phase', 16)->default('intern');
            $table->string('stage', 16)->default('interested');
            $table->string('priority', 16)->nullable();
            $table->json('tags')->nullable();

            // Mypage details. Passwords are deliberately NOT stored here:
            // keep them in a password manager.
            $table->string('mypage_url', 1024)->nullable();
            $table->string('mypage_login_id')->nullable();
            $table->string('recruit_url', 1024)->nullable();

            $table->string('test_type')->nullable();
            $table->string('next_action')->nullable();
            $table->date('result_expected_on')->nullable();
            $table->text('memo')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
