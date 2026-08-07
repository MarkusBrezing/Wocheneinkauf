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
        // Jede Familie legt ihre eigenen Maerkte an. Das ist die eigentliche
        // Voraussetzung fuer Mandantenfaehigkeit: "Storchenhof" und
        // "Heinersdorf" waeren fuer fremde Familien sinnlos.
        Schema::create('markets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['family_id', 'name']);
        });

        // Regalbereiche in Laufreihenfolge des Marktes.
        Schema::create('market_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('market_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedSmallInteger('sort')->default(0);

            $table->unique(['market_id', 'name']);
            // Ziel fuer die mandantensicheren Verbund-Fremdschluessel (R-03).
            $table->unique(['id', 'family_id']);
            $table->index(['market_id', 'sort']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_sections');
        Schema::dropIfExists('markets');
    }
};
