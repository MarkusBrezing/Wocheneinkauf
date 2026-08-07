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
        // Vorlagen fuer eine neue Einkaufsliste: "Wocheneinkauf", "Grillabend",
        // "Urlaub". Die Fixartikel (B-08) sind der Sonderfall davon -- eine
        // einzige, unbenannte Vorlage. Hier duerfen es mehrere sein, jede mit
        // eigenen Mengen und Notizen.
        Schema::create('list_templates', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            // Wird beim Anlegen einer neuen Liste automatisch uebernommen.
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['family_id', 'name']);
            // Ziel fuer den Verbund-Fremdschluessel der Positionen (R-03).
            $table->unique(['id', 'family_id']);
        });

        Schema::create('list_template_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('template_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_name', 160);
            $table->decimal('amount', 10, 3)->nullable();
            $table->string('amount_raw', 20)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('note', 160)->nullable();
            $table->foreignId('product_group_id')->nullable()
                ->constrained('product_groups')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['template_id', 'sort']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            // Wie bei den Listeneintraegen: der Verbund verhindert, dass eine
            // Position in der Vorlage einer fremden Familie landet.
            $table->foreign(['template_id', 'family_id'])
                ->references(['id', 'family_id'])->on('list_templates')->cascadeOnDelete();
        });

        // Damit sich einem Listeneintrag ansehen laesst, woher er kommt.
        // Erweitert Schema v1.1 um einen Wert -- die Vorlage ist dort noch
        // nicht vorgesehen.
        $this->quelle("'manual','recipe','pantry','standing','mealplan','template'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('shopping_list_items')->where('source', 'template')->update(['source' => 'manual']);

        $this->quelle("'manual','recipe','pantry','standing','mealplan'");

        Schema::dropIfExists('list_template_items');
        Schema::dropIfExists('list_templates');
    }

    protected function quelle(string $werte): void
    {
        DB::statement(
            "ALTER TABLE shopping_list_items MODIFY source ENUM({$werte}) NOT NULL DEFAULT 'manual'"
        );
    }
};
