<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Vorratsposition: derselbe Artikel kann an mehreren Orten liegen --
        // Nudeln in der Speisekammer und im Keller sind zwei Positionen mit
        // eigenem Bestand.
        Schema::create('pantry_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('product_id');
            // NOT NULL mit Vorgabewert, nicht nullable: InnoDB laesst im
            // UNIQUE-Key beliebig viele NULL-Duplikate zu -- der Ort waere
            // sonst kein Unterscheidungsmerkmal, sondern ein Schlupfloch.
            $table->string('location', 80)->default('Vorrat');
            $table->string('unit', 20)->nullable();
            $table->decimal('target_qty', 10, 3)->default(1);   // Soll
            $table->decimal('min_qty', 10, 3)->default(0);      // Meldebestand
            $table->decimal('current_qty', 10, 3)->default(0);  // Ist
            $table->date('best_before')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['family_id', 'product_id', 'location']);
            $table->index(['family_id', 'is_active', 'sort']);
            // Ziel fuer die Verbund-Fremdschluessel der Zeilen und Buchungen.
            $table->unique(['id', 'family_id']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['product_id', 'family_id'])
                ->references(['id', 'family_id'])->on('products')->cascadeOnDelete();
        });

        // Ein Inventurlauf. Historisiert, damit sich spaeter beantworten
        // laesst, wann zuletzt gezaehlt wurde und was dabei herauskam.
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

            $table->index(['family_id', 'status', 'started_at']);
            $table->unique(['id', 'family_id']);

            // Je Familie hoechstens ein offener Lauf -- zwei gleichzeitige
            // Zaehlungen widersprechen sich, und die Frage "welche gilt?"
            // laesst sich hinterher nicht mehr beantworten. Derselbe Trick
            // wie bei der aktiven Einkaufsliste.
            $table->unsignedTinyInteger('offen_marker')->nullable()
                ->storedAs("if(status = 'open', 1, null)");
            $table->unique(['family_id', 'offen_marker']);
        });

        Schema::create('inventory_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('inventory_id');
            $table->unsignedBigInteger('pantry_item_id');
            // Friert den Ist-Stand vor der Zaehlung ein. Ohne ihn liesse sich
            // die Abweichung hinterher nicht mehr berechnen.
            $table->decimal('expected_qty', 10, 3)->default(0);
            // NULL heisst "noch nicht gezaehlt" -- daran erkennt jeder
            // Mitzaehler, was noch offen ist (E-04, E-05).
            $table->decimal('counted_qty', 10, 3)->nullable();
            $table->decimal('target_qty', 10, 3)->default(0);
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->string('note', 160)->nullable();

            $table->unique(['inventory_id', 'pantry_item_id']);
            $table->index(['family_id']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['inventory_id', 'family_id'])
                ->references(['id', 'family_id'])->on('inventories')->cascadeOnDelete();
            $table->foreign(['pantry_item_id', 'family_id'])
                ->references(['id', 'family_id'])->on('pantry_items')->cascadeOnDelete();
        });

        // Die Differenz rechnet die Datenbank -- so kann sie nicht von der
        // Anwendung abweichen. Laravel kennt keine generierte Spalte mit
        // Bezug auf nullable Werte, deshalb per SQL.
        DB::statement('ALTER TABLE inventory_lines ADD COLUMN difference DECIMAL(10,3) '
            .'AS (counted_qty - expected_qty) STORED AFTER target_qty');

        // Das Bewegungskonto: beantwortet "warum hat sich der Bestand
        // geaendert" und ist die Grundlage fuer den spaeteren Abzug beim
        // Kochen.
        Schema::create('pantry_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('pantry_item_id');
            $table->decimal('qty_delta', 10, 3);
            $table->enum('reason', ['purchase', 'consumption', 'inventory', 'correction', 'expired']);
            $table->string('ref_type', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pantry_item_id', 'created_at']);
            $table->index(['family_id', 'created_at']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['pantry_item_id', 'family_id'])
                ->references(['id', 'family_id'])->on('pantry_items')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pantry_movements');
        Schema::dropIfExists('inventory_lines');
        Schema::dropIfExists('inventories');
        Schema::dropIfExists('pantry_items');
    }
};
