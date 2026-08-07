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
        // Ziele fuer die mandantensicheren Verbund-Fremdschluessel (R-03).
        // Warengruppen und Regalbereiche haben sie seit Schritt 3, Maerkte und
        // Artikel brauchen sie erst jetzt -- vorher zeigte nichts auf sie.
        Schema::table('markets', function (Blueprint $table) {
            $table->unique(['id', 'family_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['id', 'family_id']);
        });

        // Wo es einen Artikel gibt.
        //
        // Die Tabelle bleibt fuer den Normalfall leer: Ein Artikel ohne jede
        // Zuordnung ist ueberall erhaeltlich. Eingetragen wird nur die
        // Ausnahme -- Misopaste gibt es eben nur im Asialaden. Das ist der
        // Grund gegen ein zusaetzliches Kennzeichen am Artikel: es gaebe den
        // widerspruechlichen Zustand "eingeschraenkt, aber auf keinen Markt",
        // also einen Artikel, den es nirgends gibt.
        Schema::create('market_product', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('product_id');

            $table->primary(['market_id', 'product_id']);
            $table->index(['product_id', 'family_id']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['market_id', 'family_id'])
                ->references(['id', 'family_id'])->on('markets')->cascadeOnDelete();
            $table->foreign(['product_id', 'family_id'])
                ->references(['id', 'family_id'])->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_product');

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['id', 'family_id']);
        });

        Schema::table('markets', function (Blueprint $table) {
            $table->dropUnique(['id', 'family_id']);
        });
    }
};
