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
        // Grundlage fuer den Brute-Force-Schutz (A-14). Enthaelt IP-Adressen und
        // wird deshalb per Lazy-Cleanup nach 90 Tagen bereinigt (Review-Befund
        // R-07) -- siehe App\Core\Services\LoginAttemptRecorder.
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190);
            $table->binary('ip_address', 16)->nullable();
            $table->boolean('successful')->default(false);
            $table->timestamp('attempted_at')->useCurrent();

            $table->index(['email', 'attempted_at']);
            $table->index(['ip_address', 'attempted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
