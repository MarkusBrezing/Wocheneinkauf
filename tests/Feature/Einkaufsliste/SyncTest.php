<?php

namespace Tests\Feature\Einkaufsliste;

use Illuminate\Support\Str;

/**
 * Geraeteabgleich und Offline-Betrieb (C-07, C-08).
 */
class SyncTest extends EinkaufslisteTestCase
{
    public function test_stand_abholen_liefert_liste_und_eintraege(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');

        $antwort = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->assertOk();

        $antwort->assertJsonPath('liste.status', 'active')
            ->assertJsonPath('eintraege.0.name', 'Vollmilch')
            ->assertJsonPath('eintraege.0.menge', 2)
            ->assertJsonPath('eintraege.0.einheit', 'l')
            ->assertJsonPath('eintraege.0.abgehakt', false)
            ->assertJsonPath('eintraege.0.geloescht', false);

        $this->assertIsInt($antwort->json('liste.version'));
        $this->assertNotNull($antwort->json('server_zeit'));
    }

    public function test_delta_liefert_nur_geaendertes_einschliesslich_loeschungen(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $this->aufDieListe($user, 'Bananen');

        $marke = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json('server_zeit');

        // Die Zeitmarke hat Sekundenaufloesung; ohne diesen Sprung faellt die
        // Aenderung noch in dieselbe Sekunde.
        $this->travel(2)->seconds();

        $brot = $this->eintrag($user, 'Brot');
        $this->actingAs($user)->delete('/einkaufsliste/eintraege/'.$brot->client_uuid);
        $this->aufDieListe($user, 'Butter');

        $delta = $this->actingAs($user)->getJson('/api/einkaufsliste/stand?seit='.urlencode($marke))->assertOk();

        $eintraege = collect($delta->json('eintraege'));

        $this->assertCount(2, $eintraege, 'Unveraendertes bleibt draussen.');
        $this->assertTrue($eintraege->firstWhere('name', 'Brot')['geloescht'], 'Die Loeschung muss im Delta stehen.');
        $this->assertFalse($eintraege->firstWhere('name', 'Butter')['geloescht']);
    }

    public function test_aenderungen_werden_eingespielt(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste');
        $stand = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json();

        $uuid = (string) Str::uuid();

        $antwort = $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $stand['liste']['version'],
            'liste_id' => $stand['liste']['id'],
            'aenderungen' => [
                ['typ' => 'hinzufuegen', 'uuid' => $uuid, 'text' => '2 l Vollmilch'],
            ],
        ])->assertOk();

        $antwort->assertJsonPath('angewendet', 1)
            ->assertJsonPath('eintraege.0.uuid', $uuid)
            ->assertJsonPath('eintraege.0.name', 'Vollmilch');

        $this->assertGreaterThan($stand['liste']['version'], $antwort->json('liste.version'));

        // Abhaken und loeschen laufen ueber dieselbe client_uuid.
        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $antwort->json('liste.version'),
            'aenderungen' => [
                ['typ' => 'abhaken', 'uuid' => $uuid, 'abgehakt' => true],
            ],
        ])->assertOk()->assertJsonPath('eintraege.0.abgehakt', true)
            ->assertJsonPath('eintraege.0.abgehakt_von', $user->name);
    }

    public function test_ein_veralteter_stand_endet_in_409_mit_dem_aktuellen(): void
    {
        [$user, $family] = $this->familie();
        $zweiter = $this->mitglied($family, 'zweiter@example.test', 'member');

        $this->aufDieListe($user, 'Brot');
        $stand = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json();

        // Jemand anderes war schneller.
        $this->aufDieListe($zweiter, 'Butter');

        $antwort = $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $stand['liste']['version'],
            'aenderungen' => [
                ['typ' => 'hinzufuegen', 'uuid' => (string) Str::uuid(), 'text' => 'Käse'],
            ],
        ])->assertStatus(409);

        $antwort->assertJsonPath('fehler', 'version_konflikt')
            ->assertJsonPath('gesendete_version', $stand['liste']['version']);

        // Der Client bekommt den aktuellen Stand mitgeliefert und kann seine
        // Aenderung darauf neu anwenden.
        $this->assertGreaterThan($stand['liste']['version'], $antwort->json('liste.version'));
        $this->assertCount(2, $this->eintraege($user), 'Nichts wurde blind ueberschrieben.');
    }

    public function test_eine_wiederholte_mutation_legt_keinen_zweiten_eintrag_an(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste');
        $version = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json('liste.version');

        $uuid = (string) Str::uuid();
        $mutation = ['typ' => 'hinzufuegen', 'uuid' => $uuid, 'text' => '2 l Vollmilch'];

        $erste = $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $version,
            'aenderungen' => [$mutation],
        ])->assertOk();

        // Der Klassiker nach einem Verbindungsabbruch: derselbe Batch noch
        // einmal, diesmal mit der neuen Version.
        $zweite = $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $erste->json('liste.version'),
            'aenderungen' => [$mutation],
        ])->assertOk();

        $zweite->assertJsonPath('angewendet', 0)
            ->assertJsonPath('ignoriert', [$uuid]);

        $this->assertCount(1, $this->eintraege($user));
        $this->assertSame('2.000', $this->eintraege($user)->first()->amount, 'Die Menge darf sich nicht verdoppeln.');
    }

    public function test_eine_zusammengefuehrte_mutation_bleibt_wiederholbar(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, '2 l Vollmilch');
        $version = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json('liste.version');

        $uuid = (string) Str::uuid();
        $mutation = ['typ' => 'hinzufuegen', 'uuid' => $uuid, 'text' => '1 l Vollmilch'];

        $erste = $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $version,
            'aenderungen' => [$mutation],
        ])->assertOk()->assertJsonPath('angewendet', 1);

        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $erste->json('liste.version'),
            'aenderungen' => [$mutation],
        ])->assertOk()->assertJsonPath('angewendet', 0);

        $eintraege = $this->eintraege($user);

        $this->assertCount(1, $eintraege, 'Zusammengefuehrt statt verdoppelt (C-17).');
        $this->assertSame('3.000', $eintraege->first()->amount);

        // Der Grabstein belegt die uuid und sagt den anderen Geraeten, dass
        // ihr Eintrag aufgegangen ist.
        $this->assertDatabaseHas('shopping_list_items', ['client_uuid' => $uuid]);
        $this->assertSoftDeleted('shopping_list_items', ['client_uuid' => $uuid]);
    }

    public function test_eine_unbekannte_uuid_wird_ignoriert_statt_zu_scheitern(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $version = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json('liste.version');
        $uuid = (string) Str::uuid();

        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $version,
            'aenderungen' => [['typ' => 'loeschen', 'uuid' => $uuid]],
        ])->assertOk()
            ->assertJsonPath('angewendet', 0)
            ->assertJsonPath('ignoriert', [$uuid]);
    }

    public function test_ein_listenwechsel_wird_wie_ein_konflikt_behandelt(): void
    {
        [$user] = $this->familie();

        $this->aufDieListe($user, 'Brot');
        $stand = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json();

        // Waehrend das Geraet offline war, wurde der Einkauf abgeschlossen.
        $this->actingAs($user)->post('/einkaufsliste/archivieren');

        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $stand['liste']['version'],
            'liste_id' => $stand['liste']['id'],
            'aenderungen' => [['typ' => 'hinzufuegen', 'uuid' => (string) Str::uuid(), 'text' => 'Käse']],
        ])->assertStatus(409)->assertJsonPath('fehler', 'liste_gewechselt');
    }

    public function test_unsinnige_mutationen_werden_abgewiesen(): void
    {
        [$user] = $this->familie();

        $this->actingAs($user)->get('/einkaufsliste');
        $version = $this->actingAs($user)->getJson('/api/einkaufsliste/stand')->json('liste.version');

        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $version,
            'aenderungen' => [['typ' => 'verschenken', 'uuid' => (string) Str::uuid()]],
        ])->assertStatus(422);

        $this->actingAs($user)->postJson('/api/einkaufsliste/stand', [
            'version' => $version,
            'aenderungen' => [['typ' => 'hinzufuegen', 'uuid' => 'kein-uuid', 'text' => 'Brot']],
        ])->assertStatus(422);
    }
}
