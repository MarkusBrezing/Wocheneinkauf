<?php

namespace Tests\Feature;

use App\Core\Models\Family;
use App\Core\Models\Invitation;
use App\Core\Models\User;
use App\Core\Notifications\FamilyInvitation;
use App\Core\Services\FamilyProvisioner;
use App\Core\Services\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A-06, A-07, A-09: Einladen, Annehmen, Widerrufen.
 */
class InvitationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_kann_per_email_einladen(): void
    {
        Notification::fake();

        [$owner, $family] = $this->familieMitOwner();

        $this->actingAs($owner)
            ->post('/familie/einladungen', ['email' => 'gast@example.test', 'role' => 'member'])
            ->assertRedirect();

        $this->assertDatabaseHas('invitations', [
            'family_id' => $family->getKey(),
            'email' => 'gast@example.test',
            'role' => 'member',
        ]);

        Notification::assertSentOnDemand(FamilyInvitation::class);
    }

    public function test_nur_der_klartext_token_oeffnet_die_einladung(): void
    {
        [$owner, $family] = $this->familieMitOwner();

        ['invitation' => $einladung, 'token' => $token] = app(InvitationService::class)
            ->create($family, $owner, 'gast@example.test');

        // In der Datenbank steht nur der Hash -- wer sie liest, kann daraus
        // keinen gueltigen Link bauen.
        $this->assertNotSame($token, $einladung->token_hash);
        $this->assertSame(hash('sha256', $token), $einladung->token_hash);

        $this->get('/einladung/'.$token)->assertOk();
        $this->get('/einladung/'.$einladung->token_hash)->assertSee('gilt nicht mehr');
    }

    public function test_bestehendes_konto_kann_einladung_annehmen(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['token' => $token] = app(InvitationService::class)->create($family, $owner, null, 'admin');

        $gast = $this->nutzerMitEigenerFamilie('gast@example.test');

        $this->actingAs($gast)->post('/einladung/'.$token)->assertRedirect(route('dashboard'));

        $this->assertSame('admin', $gast->fresh()->roleIn($family));
    }

    public function test_neues_konto_tritt_ueber_die_einladung_bei_statt_eine_familie_anzulegen(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['token' => $token] = app(InvitationService::class)->create($family, $owner, 'neu@example.test');

        $this->post('/register', [
            'name' => 'Neuling',
            'email' => 'neu@example.test',
            'password' => 'ein-sicheres-passwort',
            'password_confirmation' => 'ein-sicheres-passwort',
            'einladung' => $token,
        ])->assertRedirect();

        $neu = User::where('email', 'neu@example.test')->firstOrFail();

        $this->assertSame('member', $neu->roleIn($family));
        $this->assertSame(1, $neu->families()->count(), 'Es darf keine zweite Familie entstanden sein.');
        $this->assertSame(1, Family::count());
    }

    public function test_widerrufene_einladung_ist_nicht_mehr_einloesbar(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['invitation' => $einladung, 'token' => $token] = app(InvitationService::class)
            ->create($family, $owner);

        $this->actingAs($owner)
            ->delete('/familie/einladungen/'.$einladung->getKey())
            ->assertRedirect();

        $this->assertNotNull($einladung->fresh()->revoked_at);
        $this->assertNull(app(InvitationService::class)->findRedeemable($token));
    }

    public function test_abgelaufene_einladung_ist_nicht_mehr_einloesbar(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['invitation' => $einladung, 'token' => $token] = app(InvitationService::class)
            ->create($family, $owner);

        $einladung->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->assertNull(app(InvitationService::class)->findRedeemable($token));
    }

    public function test_einladung_verbraucht_ihr_kontingent(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['invitation' => $einladung, 'token' => $token] = app(InvitationService::class)
            ->create($family, $owner);

        $gast = $this->nutzerMitEigenerFamilie('gast@example.test');
        $this->actingAs($gast)->post('/einladung/'.$token);

        $this->assertSame(1, $einladung->fresh()->used_count);
        $this->assertNull(app(InvitationService::class)->findRedeemable($token));
    }

    public function test_fremde_einladung_laesst_sich_nicht_widerrufen(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        ['invitation' => $fremde] = app(InvitationService::class)->create($family, $owner);

        $anderer = $this->nutzerMitEigenerFamilie('anderer@example.test');

        $this->actingAs($anderer)
            ->delete('/familie/einladungen/'.$fremde->getKey())
            ->assertNotFound();

        $this->assertNull($fremde->fresh()->revoked_at);
    }

    public function test_mitglied_ohne_verwaltungsrecht_darf_nicht_einladen(): void
    {
        [$owner, $family] = $this->familieMitOwner();

        $kind = User::factory()->create(['email' => 'kind@example.test']);
        app(FamilyProvisioner::class)->join($kind, $family, 'child');

        $this->actingAs($kind)
            ->post('/familie/einladungen', ['email' => 'wer@example.test', 'role' => 'member'])
            ->assertForbidden();

        $this->assertSame(0, Invitation::count());
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

    protected function nutzerMitEigenerFamilie(string $email): User
    {
        $user = User::factory()->create(['email' => $email]);
        app(FamilyProvisioner::class)->createFor($user);

        return $user->refresh();
    }
}
