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
        // Ein Plan je Kalenderwoche und Familie. week_start ist der erste Tag
        // der Planwoche -- welcher das ist, sagt families.week_starts_on.
        Schema::create('meal_plans', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->enum('status', ['draft', 'active', 'done'])->default('draft');
            // Die Einkaufsliste, in die der Plan uebernommen wurde (F-09).
            $table->foreignId('list_id')->nullable()->constrained('shopping_lists')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['family_id', 'week_start']);
            $table->unique(['id', 'family_id']);
        });

        Schema::create('meal_plan_slots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('meal_plan_id');
            $table->date('plan_date');
            $table->enum('meal', ['breakfast', 'lunch', 'dinner', 'snack']);
            $table->unsignedBigInteger('recipe_id')->nullable();
            // "Reste", "Essen gehen" -- nicht jeder Abend ist ein Rezept.
            $table->string('custom_text', 160)->nullable();
            $table->unsignedSmallInteger('servings')->nullable();
            // Gesperrte Slots ruehrt der Generator nicht an (F-05). Ohne das
            // waere jeder Wurf ein Alles-oder-nichts.
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_cooked')->default(false);
            $table->timestamp('cooked_at')->nullable();
            $table->enum('origin', ['manual', 'suggested'])->default('manual');

            $table->unique(['meal_plan_id', 'plan_date', 'meal']);
            $table->index(['recipe_id', 'plan_date']);
            $table->index(['family_id', 'plan_date']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['meal_plan_id', 'family_id'])
                ->references(['id', 'family_id'])->on('meal_plans')->cascadeOnDelete();
            // Einfacher Fremdschluessel statt Verbund: MySQL laesst
            // ON DELETE SET NULL nicht zu, wenn eine der Spalten NOT NULL ist
            // -- und family_id ist es. Die Mandantensicherheit fuer recipe_id
            // kommt deshalb wie bei allen nullable Verweisen ueber den Global
            // Scope: die ID wird nachgeladen, statt ihr zu vertrauen.
            $table->foreign('recipe_id')->references('id')->on('recipes')->nullOnDelete();
        });

        // Protokoll der Vorschlagslaeufe: macht "nochmal wuerfeln"
        // nachvollziehbar und verhindert, dass derselbe Vorschlag wiederkommt.
        Schema::create('meal_plan_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('family_id');
            $table->unsignedBigInteger('meal_plan_id');
            $table->string('strategy', 40)->default('balanced');
            $table->json('params')->nullable();
            $table->json('result');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['meal_plan_id', 'created_at']);

            $table->foreign('family_id')->references('id')->on('families')->cascadeOnDelete();
            $table->foreign(['meal_plan_id', 'family_id'])
                ->references(['id', 'family_id'])->on('meal_plans')->cascadeOnDelete();
        });

        // "Nie vorschlagen" (F-15) haengt am Rezept, nicht am Plan: Die
        // Entscheidung gilt dauerhaft und nicht nur fuer eine Woche.
        Schema::table('recipes', function (Blueprint $table) {
            $table->boolean('exclude_from_suggestions')->default(false)->after('is_favorite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn('exclude_from_suggestions');
        });

        Schema::dropIfExists('meal_plan_suggestions');
        Schema::dropIfExists('meal_plan_slots');
        Schema::dropIfExists('meal_plans');
    }
};
