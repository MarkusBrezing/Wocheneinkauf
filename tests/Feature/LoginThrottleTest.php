<?php

namespace Tests\Feature;

use App\Core\Models\LoginAttempt;
use App\Core\Models\User;
use App\Core\Services\LoginAttemptRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * A-14: Brute-Force-Schutz.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_fehlversuche_werden_protokolliert(): void
    {
        User::factory()->create(['email' => 'opfer@example.test']);

        $this->post('/login', [
            'email' => 'opfer@example.test',
            'password' => 'falsch',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'opfer@example.test',
            'successful' => 0,
        ]);
    }

    public function test_erfolgreiche_anmeldung_wird_protokolliert_und_setzt_last_login_at(): void
    {
        $user = User::factory()->create([
            'email' => 'gut@example.test',
            'password' => 'ein-sicheres-passwort',
        ]);

        $this->post('/login', [
            'email' => 'gut@example.test',
            'password' => 'ein-sicheres-passwort',
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => 'gut@example.test',
            'successful' => 1,
        ]);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_konto_wird_nach_zu_vielen_fehlversuchen_gesperrt(): void
    {
        $user = User::factory()->create([
            'email' => 'ziel@example.test',
            'password' => 'ein-sicheres-passwort',
        ]);

        $this->protokolliereFehlversuche('ziel@example.test', LoginAttemptRecorder::LOCKOUT_THRESHOLD);

        // Der Cache-Zaehler von Fortify ist leer -- die Sperre muss allein aus
        // der Tabelle kommen, damit sie einen Neustart ueberlebt.
        RateLimiter::clear('login');

        // Das richtige Passwort darf waehrend der Sperre nicht durchkommen.
        $this->post('/login', [
            'email' => 'ziel@example.test',
            'password' => 'ein-sicheres-passwort',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_eine_erfolgreiche_anmeldung_hebt_die_sperre_auf(): void
    {
        // Sonst koennte ein Fremder ein Konto aussperren, indem er es beschiesst.
        $this->protokolliereFehlversuche('ziel@example.test', LoginAttemptRecorder::LOCKOUT_THRESHOLD);

        LoginAttempt::create([
            'email' => 'ziel@example.test',
            'successful' => true,
            'attempted_at' => now(),
        ]);

        $this->assertFalse(
            app(LoginAttemptRecorder::class)->isLockedOut('ziel@example.test', '127.0.0.1')
        );
    }

    public function test_alte_eintraege_werden_beim_schreiben_aufgeraeumt(): void
    {
        // R-07: kein Cronjob verfuegbar, also lazy beim naechsten Schreibzugriff.
        LoginAttempt::create([
            'email' => 'uralt@example.test',
            'successful' => false,
            'attempted_at' => now()->subDays(LoginAttemptRecorder::RETENTION_DAYS + 1),
        ]);

        app(LoginAttemptRecorder::class)->record('neu@example.test', '127.0.0.1', false);

        $this->assertDatabaseMissing('login_attempts', ['email' => 'uralt@example.test']);
        $this->assertDatabaseHas('login_attempts', ['email' => 'neu@example.test']);
    }

    protected function protokolliereFehlversuche(string $email, int $anzahl): void
    {
        foreach (range(1, $anzahl) as $i) {
            LoginAttempt::create([
                'email' => $email,
                'ip_address' => LoginAttempt::packIp('127.0.0.1'),
                'successful' => false,
                'attempted_at' => now(),
            ]);
        }
    }
}
