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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('full_name')->nullable();
            $table->string('full_name_kana')->nullable();
            $table->string('full_name_roman')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('postal_code', 16)->nullable();
            $table->string('address')->nullable();
            $table->string('github_url')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('school');
            $table->string('faculty')->nullable();
            $table->string('department')->nullable();
            $table->string('major')->nullable();
            $table->string('degree')->nullable();
            $table->char('entered_on', 7);   // YYYY-MM
            $table->char('graduated_on', 7)->nullable(); // YYYY-MM
            $table->string('graduation_status', 16)->default('graduated'); // graduated | expected
            $table->timestamps();
        });

        Schema::create('certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('acquired_on', 7)->nullable(); // YYYY-MM
            $table->string('score')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certifications');
        Schema::dropIfExists('educations');
        Schema::dropIfExists('profiles');
    }
};
