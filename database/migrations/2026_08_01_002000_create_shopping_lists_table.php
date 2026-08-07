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
        // Eine Einkaufsliste je Einkauf. Archivierte Listen bleiben als
        // Historie stehen (C-11, C-12), die aktive ist die, an der gerade
        // gearbeitet wird.
        Schema::create('shopping_lists', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            // Optimistisches Sperren (C-07): jede Mutation zaehlt hoch. Der
            // Client sendet die ihm bekannte Version, bei Abweichung antwortet
            // die API mit 409 statt blind zu ueberschreiben.
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Je Familie darf es nur eine aktive Liste geben -- sonst waere
            // "die Einkaufsliste" nicht mehr eindeutig bestimmbar. Der UNIQUE
            // greift nur auf 'active', weil die generierte Spalte fuer
            // archivierte Listen NULL ist und NULL im Index nicht verglichen
            // wird. Das ist dieselbe Ebene 2 wie bei den Verbund-Fremd-
            // schluesseln aus Schritt 3: eine Invariante, die auch dann haelt,
            // wenn jemand an Eloquent vorbei schreibt.
            $table->unsignedTinyInteger('aktiv_marker')->nullable()
                ->storedAs("if(status = 'active', 1, null)");
            $table->unique(['family_id', 'aktiv_marker']);

            $table->index(['family_id', 'status', 'archived_at']);
            // Ziel fuer den Verbund-Fremdschluessel der Listeneintraege (R-03).
            $table->unique(['id', 'family_id']);
        });

        Schema::create('shopping_list_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('list_id');
            // Vom Browser erzeugt. Ein doppelt gesendeter POST -- der
            // Normalfall nach einem Verbindungsabbruch -- legt dank
            // UNIQUE(list_id, client_uuid) keinen zweiten Datensatz an (C-08).
            $table->char('client_uuid', 36)->charset('ascii')->collation('ascii_bin');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_name', 160);
            $table->decimal('amount', 10, 3)->nullable();
            // "1-2", "1/2", "etwas" -- alles, was sich nicht rechnen laesst.
            $table->string('amount_raw', 20)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('note', 160)->nullable();
            // Manuelle Umhaengung (C-05) gewinnt gegen die Warengruppe des
            // Artikels aus dem Katalog.
            $table->foreignId('product_group_id')->nullable()
                ->constrained('product_groups')->nullOnDelete();
            $table->boolean('is_checked')->default(false);
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->enum('source', ['manual', 'recipe', 'pantry', 'standing', 'mealplan'])->default('manual');
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            // Soft Delete, damit andere Geraete die Loeschung ueberhaupt
            // mitbekommen -- eine verschwundene Zeile ist im Delta nicht
            // unterscheidbar von einer, die es nie gab.
            $table->softDeletes();

            $table->unique(['list_id', 'client_uuid']);
            $table->index(['list_id', 'is_checked', 'sort']);
            // Der Delta-Sync fragt "alles mit updated_at > X" (C-07).
            $table->index(['family_id', 'updated_at']);
            // Nach aussen adressiert die client_uuid den Eintrag, ohne dass
            // die Liste in der URL steht. Der UNIQUE oben hilft dabei nicht --
            // er beginnt mit list_id.
            $table->index(['family_id', 'client_uuid']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            // Verbund statt einfachem Fremdschluessel (R-03): so kann ein
            // Eintrag gar nicht erst in der Liste einer fremden Familie
            // landen -- auch nicht bei einem Insert an Eloquent vorbei.
            $table->foreign(['list_id', 'family_id'])
                ->references(['id', 'family_id'])->on('shopping_lists')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shopping_list_items');
        Schema::dropIfExists('shopping_lists');
    }
};
