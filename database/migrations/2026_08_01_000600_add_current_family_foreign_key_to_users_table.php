<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * users.current_family_id -> families und families <- family_user <- users
     * bilden einen Zyklus. Der Fremdschluessel steht deshalb bewusst in einer
     * eigenen, spaeteren Migration -- sonst scheitert migrate:fresh, weil beim
     * Anlegen von users die Tabelle families noch nicht existiert
     * (Review-Befund R-09).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('current_family_id')
                ->references('id')
                ->on('families')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_family_id']);
        });
    }
};
