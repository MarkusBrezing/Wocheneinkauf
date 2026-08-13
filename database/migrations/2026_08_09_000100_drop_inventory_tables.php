<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Der Inventurlauf entfällt.
     *
     * Die Bestandsaufnahme des Grundvorrats reicht für den Haushalt: Sie
     * rechnet Soll minus Ist und schreibt die Differenz auf die Einkaufsliste
     * -- in fünf Minuten, ohne Lauf, ohne Zustand. Der vollständige
     * Inventurlauf mit eingefrorenen Erwartungswerten, Zählhistorie und
     * Abweichungsbericht war dafür der schwerere Weg und blieb ungenutzt.
     *
     * Was bleibt: der Vorrat selbst (pantry_items) samt Buchungen. Er trägt
     * die Haltbarkeitsdaten und die Bestände, mit denen der Wochenplan
     * rechnet -- nur gezählt wird künftig anders.
     */
    public function up(): void
    {
        // Zuerst die Zeilen: Sie hängen über einen Verbund-Fremdschlüssel an
        // den Läufen.
        Schema::dropIfExists('inventory_lines');
        Schema::dropIfExists('inventories');
    }

    /**
     * Reverse the migrations.
     *
     * Legt die Tabellen im Zustand von Schritt 7 wieder an -- ohne Inhalt.
     * Eine Rückkehr würde also den Rahmen wiederherstellen, nicht die Daten;
     * sie waren beim Entfernen leer.
     */
    public function down(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120)->nullable();
            $table->enum('scope', ['full', 'location', 'selection'])->default('full');
            $table->string('scope_value', 80)->nullable();
            $table->enum('status', ['open', 'completed', 'cancelled'])->default('open');
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->string('note', 255)->nullable();

            $table->unique(['id', 'family_id']);
        });

        // Die generierte Spalte samt UNIQUE: hoechstens eine offene Inventur
        // je Familie.
        DB::statement('ALTER TABLE inventories
            ADD COLUMN offen_marker TINYINT UNSIGNED
            GENERATED ALWAYS AS (if(`status` = \'open\', 1, NULL)) STORED');
        DB::statement('ALTER TABLE inventories ADD UNIQUE KEY inventories_offen (family_id, offen_marker)');

        Schema::create('inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('inventory_id');
            $table->unsignedBigInteger('pantry_item_id');
            $table->decimal('expected_qty', 10, 3)->default(0);
            $table->decimal('counted_qty', 10, 3)->nullable();
            $table->decimal('target_qty', 10, 3)->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->string('note', 160)->nullable();

            $table->unique(['inventory_id', 'pantry_item_id']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['inventory_id', 'family_id'])
                ->references(['id', 'family_id'])->on('inventories')->cascadeOnDelete();
            $table->foreign(['pantry_item_id', 'family_id'])
                ->references(['id', 'family_id'])->on('pantry_items')->cascadeOnDelete();
        });

        DB::statement('ALTER TABLE inventory_lines
            ADD COLUMN difference DECIMAL(10,3)
            GENERATED ALWAYS AS (`counted_qty` - `expected_qty`) STORED');
    }
};
