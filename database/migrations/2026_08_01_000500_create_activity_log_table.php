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
        // Aktivitaetsprotokoll je Familie (G-06). Bewusst ohne Fremdschluessel:
        // der Eintrag soll das Loeschen von Familie oder Nutzer ueberleben.
        // Enthaelt personenbezogene Daten -> Lazy-Cleanup nach 90 Tagen (R-07).
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 60);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['family_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
