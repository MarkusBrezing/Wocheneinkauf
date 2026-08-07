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
        // Rezepte (D-01 … D-08). Uebernimmt die Struktur aus data.js der
        // alten App -- Zutaten mit Originaltext, Zubereitung als Markdown,
        // Naehrwerte als JSON.
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            // Sprechender Teil der URL, je Familie eindeutig.
            $table->string('slug', 220);
            $table->unsignedSmallInteger('servings')->default(4);
            $table->unsignedSmallInteger('prep_minutes')->nullable();
            $table->unsignedSmallInteger('cook_minutes')->nullable();
            $table->enum('effort', ['quick', 'normal', 'elaborate'])->default('normal');
            $table->mediumText('instructions')->nullable();   // Markdown
            $table->text('notes')->nullable();
            $table->json('nutrition')->nullable();            // {"kcal":…,"eiweiss":…}
            // Zaehlt fuer die Pflanzenpunkte einer Woche (Wochenplan, F-06).
            $table->unsignedTinyInteger('plant_points')->default(0);
            $table->boolean('is_favorite')->default(false);
            $table->string('source_url', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['family_id', 'slug']);
            // Ziel fuer die Verbund-Fremdschluessel (R-03).
            $table->unique(['id', 'family_id']);
            $table->index(['family_id', 'name']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('recipe_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            // Der Originaltext der Zutat -- "2 EL Olivenöl, kalt gepresst".
            $table->string('raw_name', 200);
            $table->decimal('amount', 10, 3)->nullable();
            $table->string('unit', 20)->nullable();
            // Grundvorrat: steht ueblicherweise im Schrank und muss nicht bei
            // jedem Rezept mitgekauft werden (heute flag:'pantry').
            $table->boolean('is_pantry')->default(false);
            // Was auf der Einkaufsliste stehen soll, wenn es sich vom
            // Zutatennamen unterscheidet (heute buyLabel).
            $table->string('buy_label', 160)->nullable();
            // Klammer fuer Zutaten, die aus mehreren Rezepten zusammenfallen
            // sollen (heute mergeKey) -- D-06.
            $table->string('merge_key', 60)->nullable();
            $table->boolean('is_optional')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index(['recipe_id', 'sort']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['recipe_id', 'family_id'])
                ->references(['id', 'family_id'])->on('recipes')->cascadeOnDelete();
        });

        // Tags fuer die Filter aus D-01: Protein, Kueche, Ernaehrungsform …
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['protein', 'cuisine', 'diet', 'course', 'season', 'other'])->default('other');
            $table->string('name', 60);

            $table->unique(['family_id', 'type', 'name']);
            $table->unique(['id', 'family_id']);
        });

        Schema::create('recipe_tag', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('recipe_id');
            $table->unsignedBigInteger('tag_id');

            $table->primary(['recipe_id', 'tag_id']);
            $table->index(['tag_id', 'family_id']);

            $table->foreign(['recipe_id', 'family_id'])
                ->references(['id', 'family_id'])->on('recipes')->cascadeOnDelete();
            $table->foreign(['tag_id', 'family_id'])
                ->references(['id', 'family_id'])->on('tags')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recipe_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
