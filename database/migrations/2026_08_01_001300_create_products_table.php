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
        // Artikelkatalog: die gemeinsame Klammer fuer Liste, Vorrat und
        // Rezeptzutat. Erst dadurch funktioniert zuverlaessig "Zutat ist im
        // Vorrat vorhanden" -- die alte App verglich dafuer Zeichenketten.
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            // Kleingeschrieben und ohne Klammerzusatz. Traegt beim Soft Delete
            // das Suffix ~{id}, weil der UNIQUE-Key sonst die Neuanlage unter
            // demselben Namen blockieren wuerde (Review-Befund R-04).
            $table->string('name_normalized', 160);
            $table->foreignId('product_group_id')->nullable()
                ->constrained('product_groups')->nullOnDelete();
            $table->string('default_unit', 20)->nullable();
            $table->boolean('is_pantry_staple')->default(false); // Salz, Oel …
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['family_id', 'name_normalized']);
        });

        // Fixartikel: Vorlagen, die der Assistent "Neue Woche" einfuegt (B-08).
        Schema::create('standing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_name', 160);
            $table->decimal('amount', 10, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->foreignId('product_group_id')->nullable()
                ->constrained('product_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            // Zeitstempel ergaenzt gegenueber Schema v1: ohne sie waere der
            // Delta-Sync ("alles mit updated_at > X") fuer Fixartikel blind
            // (Review-Befund R-06).
            $table->timestamps();

            $table->index(['family_id', 'is_active', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('standing_items');
        Schema::dropIfExists('products');
    }
};
