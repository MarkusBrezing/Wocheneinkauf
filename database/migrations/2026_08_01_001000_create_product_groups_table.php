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
        // Fachliche Warengruppe. Wird bei Familienanlage aus einem Default-Set
        // kopiert (B-09) und ist danach frei aenderbar -- ersetzt die fest
        // verdrahtete CATEGORY_MAP der alten App.
        Schema::create('product_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);            // GEMUESE, OBST, MILCH …
            $table->string('label', 80);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['family_id', 'code']);
            // Ziel fuer die mandantensicheren Verbund-Fremdschluessel (R-03).
            $table->unique(['id', 'family_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_groups');
    }
};
