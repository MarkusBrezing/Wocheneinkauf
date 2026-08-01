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
        Schema::create('families', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('name', 120);
            $table->string('locale', 10)->default('de_DE');
            $table->string('timezone', 64)->default('Europe/Berlin');
            // 1=Mo … 7=So; 6=Samstag entspricht der heutigen Logik der Alt-App
            $table->unsignedTinyInteger('week_starts_on')->default(6);
            $table->enum('status', ['active', 'suspended', 'deleted'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('families');
    }
};
