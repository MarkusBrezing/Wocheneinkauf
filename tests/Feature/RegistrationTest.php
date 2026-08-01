<?php

namespace Tests\Feature;

use App\Core\Models\Family;
use App\Core\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A-01: Registrieren legt automatisch eine Familie an, der Nutzer wird Owner.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrierung_legt_familie_an_und_macht_nutzer_zum_owner(): void
    {
        $this->post('/register', [
            'name' => 'Markus',
            'email' => 'markus@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
            'family_name' => 'Familie Brezing',
        ])->assertRedirect();

        $user = User::where('email', 'markus@example.test')->firstOrFail();
        $family = Family::where('name', 'Familie Brezing')->firstOrFail();

        $this->assertSame('owner', $user->roleIn($family));
        $this->assertSame($family->getKey(), $user->current_family_id);
    }

    public function test_ohne_familiennamen_entsteht_ein_name_aus_dem_nutzernamen(): void
    {
        $this->post('/register', [
            'name' => 'Anna',
            'email' => 'anna@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertRedirect();

        $this->assertDatabaseHas('families', ['name' => 'Familie Anna']);
    }

    public function test_public_id_wird_automatisch_vergeben(): void
    {
        $this->post('/register', [
            'name' => 'Bea',
            'email' => 'bea@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ]);

        $user = User::where('email', 'bea@example.test')->firstOrFail();

        $this->assertSame(26, strlen($user->public_id));
        $this->assertSame(26, strlen($user->currentFamily->public_id));
    }

    public function test_registrierung_verlangt_eine_freie_adresse(): void
    {
        User::factory()->create(['email' => 'belegt@example.test']);

        $this->post('/register', [
            'name' => 'Doppelt',
            'email' => 'belegt@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertSessionHasErrors('email');
    }
}
