<?php

namespace Tests\Feature;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A-11, A-12: Auskunft und Loeschung nach DSGVO.
 */
class AccountPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_auskunft_enthaelt_konto_und_familien(): void
    {
        [$user, $family] = $this->familieMitOwner();

        $antwort = $this->actingAs($user)->get('/konto/auskunft')
            ->assertOk()
            ->assertHeader('Content-Disposition', 'attachment; filename="wocheneinkauf-auskunft.json"');

        $daten = $antwort->json();

        $this->assertSame($user->email, $daten['konto']['email']);
        $this->assertSame($user->public_id, $daten['konto']['id']);
        $this->assertSame($family->name, $daten['familien'][0]['name']);
        $this->assertSame('owner', $daten['familien'][0]['rolle']);
    }

    public function test_kontoloeschung_anonymisiert_die_adresse_und_gibt_sie_wieder_frei(): void
    {
        // Review-Befund R-04: users.email ist UNIQUE und die Tabelle hat
        // deleted_at. Ohne Anonymisierung koennte sich niemand mit derselben
        // Adresse erneut registrieren.
        [$user] = $this->familieMitOwner();
        $adresse = $user->email;

        $this->actingAs($user)
            ->delete('/konto', ['password' => 'password'])
            ->assertRedirect('/');

        $this->assertSoftDeleted('users', ['id' => $user->getKey()]);
        $this->assertDatabaseMissing('users', ['email' => $adresse]);
        $this->assertDatabaseHas('users', ['email' => 'deleted-'.$user->getKey().'@invalid']);

        $this->post('/register', [
            'name' => 'Neuanfang',
            'email' => $adresse,
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
        ])->assertSessionHasNoErrors();
    }

    public function test_kontoloeschung_verlangt_das_passwort(): void
    {
        [$user] = $this->familieMitOwner();

        $this->actingAs($user)
            ->delete('/konto', ['password' => 'falsch'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $user->getKey()]);
    }

    public function test_letzter_owner_kann_nicht_gehen_solange_andere_mitglieder_da_sind(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        $mitglied = User::factory()->create(['email' => 'bleibt@example.test']);
        app(FamilyProvisioner::class)->join($mitglied, $family);

        $this->actingAs($owner)
            ->delete('/konto', ['password' => 'password'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $owner->getKey()]);
    }

    public function test_alleiniges_mitglied_nimmt_seine_familie_mit(): void
    {
        [$owner, $family] = $this->familieMitOwner();

        $this->actingAs($owner)->delete('/konto', ['password' => 'password']);

        $this->assertDatabaseMissing('families', ['id' => $family->getKey()]);
    }

    public function test_owner_kann_familie_mit_allen_daten_loeschen(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        $mitglied = User::factory()->create(['email' => 'mit@example.test']);
        app(FamilyProvisioner::class)->join($mitglied, $family);

        $this->actingAs($owner)->delete('/konto/familie', [
            'password' => 'password',
            'bestaetigung' => $family->name,
        ])->assertRedirect(route('dashboard'));

        // Endgueltig, nicht als Soft Delete: Art. 17 verlangt echtes Loeschen.
        $this->assertDatabaseMissing('families', ['id' => $family->getKey()]);
        $this->assertDatabaseMissing('family_user', ['family_id' => $family->getKey()]);
        $this->assertNull($mitglied->fresh()->current_family_id);
    }

    public function test_familienloeschung_verlangt_den_namen_als_bestaetigung(): void
    {
        [$owner, $family] = $this->familieMitOwner();

        $this->actingAs($owner)->delete('/konto/familie', [
            'password' => 'password',
            'bestaetigung' => 'irgendwas',
        ])->assertSessionHasErrors('bestaetigung');

        $this->assertDatabaseHas('families', ['id' => $family->getKey()]);
    }

    public function test_nicht_owner_darf_die_familie_nicht_loeschen(): void
    {
        [, $family] = $this->familieMitOwner();
        $mitglied = User::factory()->create(['email' => 'mit@example.test']);
        app(FamilyProvisioner::class)->join($mitglied, $family);

        $this->actingAs($mitglied->refresh())->delete('/konto/familie', [
            'password' => 'password',
            'bestaetigung' => $family->name,
        ])->assertForbidden();

        $this->assertDatabaseHas('families', ['id' => $family->getKey()]);
    }

    /**
     * @return array{0: User, 1: Family}
     */
    protected function familieMitOwner(): array
    {
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $family = app(FamilyProvisioner::class)->createFor($owner, 'Familie Test');

        return [$owner->refresh(), $family];
    }
}
