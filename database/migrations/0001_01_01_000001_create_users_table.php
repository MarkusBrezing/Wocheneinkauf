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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->char('public_id', 26)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            // Zuletzt gewaehlter Mandant. Der Fremdschluessel folgt bewusst erst in
            // einer spaeteren Migration, sonst scheitert migrate:fresh an der
            // zirkulaeren Abhaengigkeit users <-> families (Review-Befund R-09).
            $table->foreignId('current_family_id')->nullable();
            $table->string('locale', 10)->default('de_DE');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            // Abweichung von Schema v1.1 (dort: token_hash CHAR(64), SHA-256):
            // Laravels DatabaseTokenRepository nutzt fest verdrahtet die Spalte
            // "token" und hasht mit bcrypt. Der Klartext-Token wird also ebenfalls
            // nie gespeichert -- die Schutzabsicht des Schemas bleibt erfuellt.
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id', 128)->primary();
            $table->foreignId('user_id')->nullable()->index();
            // Abweichung von Schema v1.1 (dort: VARBINARY(16)): Laravels
            // Session-Handler schreibt die IP als Zeichenkette. Eine IPv6-Adresse
            // in Textform braucht bis zu 45 Zeichen und wuerde in 16 Byte
            // abgeschnitten.
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->mediumText('payload');
            $table->unsignedInteger('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
