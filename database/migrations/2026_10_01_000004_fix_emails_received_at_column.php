<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * MariaDB / older MySQL give the first NOT NULL timestamp column
     * "ON UPDATE CURRENT_TIMESTAMP", so every save overwrote received_at.
     * A datetime column has no such behaviour.
     */
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dateTime('received_at')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->timestamp('received_at')->change();
        });
    }
};
