<?php

namespace Tests\Feature\Einkaufsliste;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gemeinsamer Unterbau der Tests zur Einkaufsliste.
 *
 * Anders als in Schritt 3 stehen die Helfer nicht in jeder Testklasse: die
 * Liste bringt fuenf Testdateien mit, und der Griff in den Familienkontext
 * kommt in jeder von ihnen vor.
 */
abstract class EinkaufslisteTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Nutzer samt frisch bereitgestellter Familie -- inklusive Startdatensatz
     * aus B-09, also 15 Warengruppen und dem Markt "Supermarkt".
     *
     * @return array{0: User, 1: Family}
     */
    protected function familie(string $email = 'owner@example.test', string $name = 'Familie Test'): array
    {
        $user = User::factory()->create(['email' => $email]);
        $family = app(FamilyProvisioner::class)->createFor($user, $name);

        return [$user->refresh(), $family];
    }

    /**
     * Zweites Mitglied ohne Verwaltungsrechte.
     */
    protected function mitglied(Family $family, string $email = 'kind@example.test', string $role = 'child'): User
    {
        $user = User::factory()->create(['email' => $email]);

        app(FamilyProvisioner::class)->join($user, $family, $role);

        return $user->refresh();
    }

    /**
     * Callback im Mandantenkontext des Nutzers ausfuehren -- ohne ihn liefert
     * der Global Scope bewusst nichts (FamilyScope).
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    protected function imKontext(User $user, Closure $callback): mixed
    {
        return app(FamilyContext::class)->runFor($user->current_family_id, $callback);
    }

    protected function aktiveListe(User $user): ShoppingList
    {
        return $this->imKontext($user, fn () => ShoppingList::active()->firstOrFail());
    }

    /**
     * @return Collection<int, ShoppingListItem>
     */
    protected function eintraege(User $user, ?ShoppingList $liste = null): Collection
    {
        return $this->imKontext($user, function () use ($liste) {
            $liste ??= ShoppingList::active()->firstOrFail();

            return $liste->items()->orderBy('id')->get();
        });
    }

    protected function eintrag(User $user, string $name): ShoppingListItem
    {
        return $this->imKontext($user, fn () => ShoppingListItem::where('raw_name', $name)->firstOrFail());
    }

    /**
     * Artikel ueber das Formular auf die Liste setzen (C-01).
     */
    protected function aufDieListe(User $user, string $text, ?int $gruppeId = null): void
    {
        $this->actingAs($user)
            ->post('/einkaufsliste/eintraege', array_filter([
                'text' => $text,
                'product_group_id' => $gruppeId,
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }
}
