<?php

namespace Tests\Feature;

use App\Core\Models\FamilySetting;
use App\Core\Models\User;
use App\Core\Scopes\FamilyScope;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mandantentrennung: der Global Scope aus Konzept 2.1 ersetzt die vergessene
 * WHERE-Klausel. Stellvertretend geprueft an FamilySetting -- ab Schritt 3
 * haengen alle Fachtabellen an demselben Trait.
 */
class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_abfragen_sehen_nur_die_eigene_familie(): void
    {
        [$a, $b] = $this->zweiFamilien();
        $ctx = app(FamilyContext::class);

        $ctx->runFor($a, fn () => FamilySetting::create(['setting_key' => 'thema', 'value' => ['x' => 'a']]));
        $ctx->runFor($b, fn () => FamilySetting::create(['setting_key' => 'thema', 'value' => ['x' => 'b']]));

        $this->assertSame(1, $ctx->runFor($a, fn () => FamilySetting::count()));
        $this->assertSame(
            ['x' => 'a'],
            $ctx->runFor($a, fn () => FamilySetting::first()->value)
        );
        $this->assertSame(2, FamilySetting::withoutGlobalScope(FamilyScope::class)->count());
    }

    public function test_ohne_kontext_liefert_der_scope_nichts_statt_alles(): void
    {
        // Fail closed: eine fehlende Kontextsetzung darf kein Datenleck werden.
        [$a] = $this->zweiFamilien();

        app(FamilyContext::class)->runFor($a, fn () => FamilySetting::create([
            'setting_key' => 'thema',
            'value' => ['x' => 'a'],
        ]));

        $this->assertSame(0, FamilySetting::count());
    }

    public function test_family_id_wird_beim_anlegen_automatisch_gesetzt(): void
    {
        [$a] = $this->zweiFamilien();

        $setting = app(FamilyContext::class)->runFor($a, fn () => FamilySetting::create([
            'setting_key' => 'thema',
            'value' => ['x' => 'a'],
        ]));

        $this->assertSame($a, $setting->family_id);
    }

    public function test_der_angemeldete_nutzer_bestimmt_den_kontext(): void
    {
        [$a] = $this->zweiFamilien();

        $user = User::factory()->create(['email' => 'wer@example.test']);
        $user->forceFill(['current_family_id' => $a])->save();

        $this->actingAs($user);

        $this->assertSame($a, app(FamilyContext::class)->id());
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function zweiFamilien(): array
    {
        $provisioner = app(FamilyProvisioner::class);

        $a = $provisioner->createFor(User::factory()->create(['email' => 'a@example.test']), 'Familie A');
        $b = $provisioner->createFor(User::factory()->create(['email' => 'b@example.test']), 'Familie B');

        return [$a->getKey(), $b->getKey()];
    }
}
