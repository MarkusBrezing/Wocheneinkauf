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
        // Stichwortregeln fuer die Auto-Kategorisierung (B-07), heute
        // KEYWORD_RULES im JavaScript. Serverseitig gepflegt, damit jede
        // Familie eigene Regeln ergaenzen kann.
        Schema::create('product_group_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_group_id')->constrained()->cascadeOnDelete();
            $table->string('pattern', 120);
            $table->boolean('is_regex')->default(false);
            // Kleinere Zahl gewinnt: spezielle Regeln vor allgemeinen.
            $table->unsignedSmallInteger('priority')->default(100);

            $table->index(['family_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_group_rules');
    }
};
