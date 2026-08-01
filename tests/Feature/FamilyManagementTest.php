<?php

namespace Tests\Feature;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A-08, A-10, A-13: Mitglieder, Einstellungen, Familienwechsel.
 */
class FamilyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_kann_familie_umbenennen_und_wochenstart_setzen(): void
    {
        [$owner, $family] = $this->familieMitOwner();

        $this->actingAs($owner)->put('/familie', [
            'name' => 'Neuer Name',
            'timezone' => 'Europe/Vienna',
            'week_starts_on' => 1,
        ])->assertRedirect();

        $family->refresh();

        $this->assertSame('Neuer Name', $family->name);
        $this->assertSame('Europe/Vienna', $family->timezone);
        $this->assertSame(1, $family->week_starts_on);
    }

    public function test_unbekannte_zeitzone_wird_abgelehnt(): void
    {
        [$owner] = $this->familieMitOwner();

        $this->actingAs($owner)->put('/familie', [
            'name' => 'Egal',
            'timezone' => 'Mars/Olympus_Mons',
            'week_starts_on' => 1,
        ])->assertSessionHasErrors('timezone');
    }

    public function test_owner_kann_rolle_eines_mitglieds_aendern(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        $mitglied = $this->mitglied($family, 'kind@example.test', 'child');

        $this->actingAs($owner)
            ->patch('/familie/mitglieder/'.$mitglied->public_id, ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame('admin', $mitglied->fresh()->roleIn($family));
    }

    public function test_owner_kann_mitglied_entfernen_und_dessen_kontext_wird_umgehaengt(): void
    {
        [$owner, $family] = $this->familieMitOwner();
        $mitglied = $this->mitglied($family, 'weg@example.test');

        $this->assertSame($family->getKey(), $mitglied->current_family_id);

        $this->actingAs($owner)
            ->delete('/familie/mitglieder/'.$mitglied->public_id)
            ->assertRedirect();

        $mitglied->refresh();

        $this->assertNull($mitglied->roleIn($family));
        $this->assertNull($mitglied->current_family_id, 'Ohne weitere Familie bleibt kein Kontext übrig.');
    }

    public function test_die_letzte_owner_rolle_laesst_sich_nicht_abgeben(): void
    {
        // Sonst bliebe die Familie unverwaltbar zurueck.
        [$owner, $family] = $this->familieMitOwner();
        $zweiter = $this->mitglied($family, 'zweiter@example.test');
        $family->users()->updateExistingPivot($zweiter->getKey(), ['role' => 'owner']);

        $this->actingAs($zweiter)
            ->patch('/familie/mitglieder/'.$owner->public_id, ['role' => 'member'])
            ->assertRedirect();
        $this->assertSame('member', $owner->fresh()->roleIn($family));

        // Jetzt ist nur noch einer Owner -- der darf sich nicht selbst degradieren.
        $this->actingAs($zweiter)
            ->patch('/familie/mitglieder/'.$zweiter->public_id, ['role' => 'member'])
            ->assertSessionHasErrors('role');
        $this->assertSame('owner', $zweiter->fresh()->roleIn($family));
    }

    public function test_mitglied_ohne_ownerrolle_darf_niemanden_entfernen(): void
    {
        [, $family] = $this->familieMitOwner();
        $a = $this->mitglied($family, 'a@example.test');
        $b = $this->mitglied($family, 'b@example.test');

        $this->actingAs($a)
            ->delete('/familie/mitglieder/'.$b->public_id)
            ->assertForbidden();

        $this->assertSame('member', $b->fresh()->roleIn($family));
    }

    public function test_nutzer_kann_zwischen_seinen_familien_wechseln(): void
    {
        [$user, $erste] = $this->familieMitOwner();
        $zweite = app(FamilyProvisioner::class)->createFor(
            User::factory()->create(['email' => 'andere@example.test']),
            'Zweite Familie'
        );
        app(FamilyProvisioner::class)->join($user, $zweite);

        $this->actingAs($user)
            ->post('/familie/wechseln', ['family' => $zweite->public_id])
            ->assertRedirect();

        $this->assertSame($zweite->getKey(), $user->fresh()->current_family_id);
        $this->assertNotSame($erste->getKey(), $user->fresh()->current_family_id);
    }

    public function test_wechsel_in_eine_fremde_familie_wird_abgelehnt(): void
    {
        [$user] = $this->familieMitOwner();
        $fremde = app(FamilyProvisioner::class)->createFor(
            User::factory()->create(['email' => 'fremd@example.test']),
            'Fremde Familie'
        );

        $this->actingAs($user)
            ->post('/familie/wechseln', ['family' => $fremde->public_id])
            ->assertSessionHasErrors('family');
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

    protected function mitglied(Family $family, string $email, string $rolle = 'member'): User
    {
        $user = User::factory()->create(['email' => $email]);
        app(FamilyProvisioner::class)->join($user, $family, $rolle);

        return $user->refresh();
    }
}
