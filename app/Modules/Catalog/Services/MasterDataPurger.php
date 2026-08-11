<?php

namespace App\Modules\Catalog\Services;

use App\Core\Models\Family;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Models\ProductGroupRule;
use App\Modules\Markets\Models\Market;
use App\Modules\Markets\Models\MarketSection;
use App\Modules\Pantry\Models\PantryItem;
use App\Modules\Pantry\Models\PantryMovement;
use App\Modules\Recipes\Models\RecipeIngredient;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Illuminate\Support\Facades\DB;

/**
 * Alle Stammdaten einer Familie loeschen: Artikel, Warengruppen, Maerkte.
 *
 * Gedacht fuer den einen Fall, fuer den es sonst keinen Weg gibt: Der
 * Startdatensatz passt nicht zur Familie, und es ist schneller, bei null
 * anzufangen, als hundertzwanzig Zeilen einzeln wegzuklicken.
 *
 * Der heikle Teil ist nicht das Loeschen, sondern die **Folgen**. Die
 * Datenbank raeumt an mehreren Stellen mit auf, und zwar unterschiedlich:
 *
 *   pantry_items.product_id      CASCADE  -> der Vorrat verschwindet mit
 *   market_product.product_id    CASCADE  -> Verfuegbarkeiten verschwinden
 *   product_group_rules          CASCADE  -> Stichwortregeln verschwinden
 *   market_sections              CASCADE  -> Regalbereiche verschwinden
 *   shopping_list_items.*_id     SET NULL -> Eintraege bleiben, ohne Verweis
 *   recipe_ingredients.product_id SET NULL -> Zutaten bleiben, ohne Verweis
 *
 * Deshalb liefert diese Klasse ein Verzeichnis der Folgen, bevor irgendetwas
 * passiert (vorschau()). Eine Sicherheitsabfrage, die nur "wirklich?" fragt,
 * ohne zu sagen, dass der Vorrat mitgeht, ist keine.
 */
class MasterDataPurger
{
    public function __construct(protected StarterDataInstaller $starterData) {}

    /**
     * Was geloescht wuerde -- und was dabei mit verschwindet.
     *
     * @return array{
     *     stammdaten: array<string, int>,
     *     mit_geloescht: array<string, int>,
     *     verweise_verlieren: array<string, int>,
     *     gesamt: int
     * }
     */
    public function vorschau(): array
    {
        $stammdaten = [
            'Artikel' => Product::withTrashed()->count(),
            'Warengruppen' => ProductGroup::count(),
            'Märkte' => Market::count(),
        ];

        return [
            'stammdaten' => $stammdaten,
            'mit_geloescht' => [
                'Stichwortregeln' => ProductGroupRule::count(),
                'Regalbereiche' => MarketSection::count(),
                'Vorratspositionen' => PantryItem::count(),
                'Vorratsbuchungen' => PantryMovement::count(),
            ],
            'verweise_verlieren' => [
                'Einträge auf Einkaufslisten' => ShoppingListItem::withTrashed()->count(),
                'Rezeptzutaten' => RecipeIngredient::count(),
            ],
            'gesamt' => array_sum($stammdaten),
        ];
    }

    /**
     * Loeschen. Alles oder nichts -- eine halb geleerte Stammdatenbasis waere
     * schlimmer als eine volle.
     *
     * Artikel werden **hart** geloescht (forceDelete). Weiches Loeschen waere
     * hier sinnlos: Wer die Stammdaten zuruecksetzt, will sie weg haben und
     * nicht unsichtbar behalten -- und nur der harte Weg loest die
     * Aufraeumregeln der Datenbank aus.
     *
     * @return array<string, int> Was tatsaechlich geloescht wurde
     */
    public function purge(Family $family, bool $startdatensatzNeu = false): array
    {
        $geloescht = DB::transaction(function () {
            // Reihenfolge von aussen nach innen: erst die Artikel (sie haengen
            // an Warengruppen), dann die Gruppen, dann die Maerkte. Umgekehrt
            // wuerde dieselbe Menge geloescht, aber ueber Kaskaden statt
            // sichtbar -- und die Zaehlung waere falsch.
            $artikel = Product::withTrashed()->get()->each->forceDelete()->count();
            $gruppen = ProductGroup::get()->each->delete()->count();
            $maerkte = Market::get()->each->delete()->count();

            return [
                'Artikel' => $artikel,
                'Warengruppen' => $gruppen,
                'Märkte' => $maerkte,
            ];
        });

        // Der Startdatensatz kommt bewusst *nach* der Transaktion: Er ist eine
        // eigene Entscheidung und darf das Loeschen nicht zurueckrollen, wenn
        // an ihm etwas schiefgeht.
        if ($startdatensatzNeu) {
            $this->starterData->install($family);
        }

        return $geloescht;
    }
}
