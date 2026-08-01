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
        // Freie Einstellungen je Familie, damit neue Module keine
        // Schemaaenderung brauchen.
        Schema::create('family_settings', function (Blueprint $table) {
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key', 80);
            $table->json('value');
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->primary(['family_id', 'setting_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_settings');
    }
};
