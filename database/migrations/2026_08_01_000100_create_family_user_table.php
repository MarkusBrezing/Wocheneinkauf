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
        // n:m, damit ein Nutzer spaeter in mehreren Familien sein kann
        // (getrennt lebende Eltern, Grosseltern). Die UI beschraenkt das
        // zunaechst auf genau eine Familie.
        Schema::create('family_user', function (Blueprint $table) {
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'admin', 'member', 'child'])->default('member');
            $table->timestamp('joined_at')->useCurrent();

            $table->primary(['family_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('family_user');
    }
};
