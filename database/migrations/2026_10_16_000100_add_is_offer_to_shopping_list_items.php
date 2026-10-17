<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Aktionsangebote auf der Einkaufsliste markieren.
 *
 * Wer den Prospekt durchgeht, schreibt "Butter" auf die Liste -- und meint
 * die Butter, die diese Woche im Angebot ist. Im Laden ist das eine andere
 * Packung an einer anderen Stelle, oft auf dem Aktionstisch statt im Kühlregal.
 * Ohne Markierung kauft man die reguläre zum vollen Preis.
 *
 * Eine eigene Spalte statt eines Vermerks in der Notiz: Die Notiz ist Freitext
 * und lässt sich weder filtern noch hervorheben.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->boolean('is_offer')->default(false)->after('is_checked');
        });
    }

    public function down(): void
    {
        Schema::table('shopping_list_items', function (Blueprint $table) {
            $table->dropColumn('is_offer');
        });
    }
};
