<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * n:m zwischen Regalbereich und Warengruppe -- ein Bereich kann mehrere
     * Gruppen buendeln ("Milchprodukte / Eier" = MILCH + EIER).
     *
     * Der Kern von Review-Befund R-03: Die Tabelle fuehrt family_id und
     * referenziert die Eltern ueber den *Verbund* (id, family_id) statt nur
     * ueber id. Damit verhindert die Datenbank selbst, dass ein Regalbereich
     * von Familie A mit einer Warengruppe von Familie B verknuepft wird --
     * eine zweite Verteidigungslinie hinter dem Global Scope, die auch dann
     * greift, wenn jemand per phpMyAdmin direkt in die Tabellen schreibt.
     */
    public function up(): void
    {
        Schema::create('market_section_groups', function (Blueprint $table) {
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('market_section_id');
            $table->unsignedBigInteger('product_group_id');

            $table->primary(['market_section_id', 'product_group_id']);
            $table->index(['product_group_id', 'family_id']);

            $table->foreign(['market_section_id', 'family_id'], 'fk_msg_section')
                ->references(['id', 'family_id'])
                ->on('market_sections')
                ->cascadeOnDelete();

            $table->foreign(['product_group_id', 'family_id'], 'fk_msg_group')
                ->references(['id', 'family_id'])
                ->on('product_groups')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_section_groups');
    }
};
