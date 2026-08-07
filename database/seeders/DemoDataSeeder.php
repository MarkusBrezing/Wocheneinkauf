<?php

namespace Database\Seeders;

use App\Core\Models\Family;
use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use App\Core\Support\FamilyContext;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Catalog\Services\ProductCategorizer;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Models\ListTemplate;
use App\Modules\ShoppingList\Models\ShoppingList;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use App\Modules\ShoppingList\Services\ItemTextParser;
use App\Modules\ShoppingList\Services\ListArchiver;
use App\Modules\ShoppingList\Services\ShoppingListManager;
use App\Modules\ShoppingList\Services\TemplateApplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Eine Demofamilie mit Alltagsdaten: Artikelkatalog samt Grundvorrat, drei
 * Maerkten, Vorlagen, einer laufenden Einkaufsliste und drei abgeschlossenen
 * Einkaeufen im Archiv.
 *
 * Die Historie entsteht ueber dieselben Dienste wie im Betrieb -- die
 * archivierten Listen wurden also wirklich abgeschlossen, samt Uebernahme der
 * fehlenden Artikel in die jeweils naechste Woche. Nur so zeigen die Daten
 * auch das Verhalten und nicht bloss gefuellte Tabellen.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Der Seeder ist additiv: sind die Demodaten schon da, tut er nichts.
 */
class DemoDataSeeder extends Seeder
{
    protected const OWNER_EMAIL = 'demo@wocheneinkauf.test';

    protected const PASSWORT = 'passwort';

    public function run(FamilyProvisioner $provisioner, ShoppingListManager $manager, ListArchiver $archiver, ItemTextParser $parser): void
    {
        if (User::where('email', self::OWNER_EMAIL)->exists()) {
            $this->command?->warn('Demodaten sind bereits vorhanden — übersprungen.');

            return;
        }

        [$owner, $partner, $kind, $family] = $this->familieAnlegen($provisioner);

        app(FamilyContext::class)->runFor($family->getKey(), function () use ($owner, $partner, $kind, $manager, $archiver, $parser) {
            $this->weitereMaerkte();
            $this->artikelkatalog();
            $this->verfuegbarkeit();
            $this->grundvorrat();

            // Drei Wochen Historie, jede mit dem Abschluss der vorherigen.
            $this->einkauf($manager, $archiver, $parser, $owner, $partner, 21, [
                '2 l Vollmilch', '1 Pck Butter', '10 Eier', 'Vollkornbrot', '1 kg Kartoffeln',
                '500 g Hackfleisch', 'Spaghetti', 'Passierte Tomaten', 'Spülmittel',
            ], offenLassen: ['Spülmittel']);

            $this->einkauf($manager, $archiver, $parser, $owner, $kind, 14, [
                '2 l Vollmilch', '1 Pck Butter', 'Naturjoghurt', 'Bananen', '2 Gurken',
                '1-2 Zwiebeln, rote', 'Hähnchenbrust', 'Basmatireis', 'Kaffee, gemahlen',
            ], offenLassen: ['Hähnchenbrust', 'Kaffee']);

            $this->einkauf($manager, $archiver, $parser, $owner, $partner, 7, [
                '2 l Vollmilch', 'Brötchen', 'Gouda', '1 kg Äpfel', 'Tomaten',
                '250 g Lachs', 'Olivenöl', 'Klopapier',
            ], offenLassen: ['Lachs']);

            // Und die Liste, an der gerade gearbeitet wird.
            $this->laufendeListe($manager, $parser, $owner, $partner);

            $this->vorlagen($parser);
        });

        $this->bericht($family);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Family}
     */
    protected function familieAnlegen(FamilyProvisioner $provisioner): array
    {
        $owner = $this->nutzer('Demo Owner', self::OWNER_EMAIL);
        $family = $provisioner->createFor($owner, 'Familie Demo');

        $partner = $this->nutzer('Demo Partner', 'partner@wocheneinkauf.test');
        $provisioner->join($partner, $family, 'admin');

        $kind = $this->nutzer('Demo Kind', 'kind@wocheneinkauf.test');
        $provisioner->join($kind, $family, 'child');

        return [$owner->refresh(), $partner->refresh(), $kind->refresh(), $family];
    }

    protected function nutzer(string $name, string $email): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORT),
        ]);

        // Ohne bestaetigte Adresse kaeme man an der verified-Middleware nicht
        // vorbei -- und Demodaten, bei denen man sich nicht anmelden kann,
        // helfen niemandem.
        $user->email_verified_at = now();
        $user->save();

        return $user;
    }

    /**
     * Zwei weitere Maerkte mit eigener Laufreihenfolge -- damit sich das
     * Umschalten (C-06) ueberhaupt bemerkbar macht und es einen Laden gibt,
     * in dem es nicht alles gibt.
     */
    protected function weitereMaerkte(): void
    {
        $maerkte = [
            ['Hofladen', 10, [
                ['Feldfrüchte', ['OBST_GEMUESE']],
                ['Hofkühlung', ['MILCH', 'EIER', 'FLEISCH']],
                ['Backhaus', ['BACKWAREN']],
                ['Regal', ['KONSERVEN', 'TROCKEN', 'GEWUERZE', 'SONSTIGES']],
            ]],
            ['Asialaden', 20, [
                ['Frische', ['OBST_GEMUESE', 'FLEISCH', 'FISCH']],
                ['Kühlung', ['MILCH', 'TIEFKUEHL']],
                ['Trockenregal', ['TROCKEN', 'KONSERVEN', 'GEWUERZE', 'SUESS', 'SONSTIGES']],
                ['Getränke', ['GETRAENKE']],
            ]],
        ];

        foreach ($maerkte as [$name, $sort, $bereiche]) {
            $markt = Market::create(['name' => $name, 'sort' => $sort]);

            foreach ($bereiche as $i => [$bereichName, $codes]) {
                $bereich = $markt->sections()->create([
                    'name' => $bereichName,
                    'sort' => ($i + 1) * 10,
                    'family_id' => $markt->family_id,
                ]);

                $ids = ProductGroup::whereIn('code', $codes)->pluck('id');

                $bereich->productGroups()->sync(
                    $ids->mapWithKeys(fn ($id) => [$id => ['family_id' => $bereich->family_id]])->all()
                );
            }
        }
    }

    /**
     * Wo es die Ausnahmen gibt. Alles, was hier nicht steht, ist ueberall
     * erhaeltlich -- Tomaten bekommt man an jeder Ecke, Misopaste nicht.
     */
    protected function verfuegbarkeit(): void
    {
        $nurBei = [
            'Misopaste' => ['Asialaden'],
            'Reisessig' => ['Asialaden'],
            'Sojasauce' => ['Asialaden', 'Supermarkt'],
            'Rohmilch' => ['Hofladen'],
        ];

        foreach ($nurBei as $artikel => $marktnamen) {
            $produkt = Product::matchingName($artikel)->first();

            if ($produkt === null) {
                continue;
            }

            $ids = Market::whereIn('name', $marktnamen)->pluck('id');

            $produkt->markets()->sync(
                $ids->mapWithKeys(fn ($id) => [$id => ['family_id' => $produkt->family_id]])->all()
            );
        }
    }

    /**
     * Artikelkatalog (B-06). Die Warengruppe bestimmen die Stichwortregeln
     * (B-07) -- so, wie es beim Anlegen ueber die Oberflaeche auch waere.
     */
    protected function artikelkatalog(): void
    {
        $artikel = [
            ['Vollmilch', 'l', false], ['Butter', 'Pck', false], ['Naturjoghurt', 'g', false],
            ['Gouda', 'g', false], ['Eier', 'Stk', false], ['Vollkornbrot', null, false],
            ['Brötchen', 'Stk', false], ['Bananen', null, false], ['Äpfel', 'kg', false],
            ['Tomaten', null, false], ['Gurken', 'Stk', false], ['Kartoffeln', 'kg', false],
            ['Zwiebeln', null, false], ['Hackfleisch', 'g', false], ['Hähnchenbrust', 'g', false],
            ['Lachs', 'g', false], ['Spaghetti', 'Pck', true], ['Basmatireis', 'Pck', true],
            ['Passierte Tomaten', 'Glas', true], ['Olivenöl', 'Fl', true], ['Salz', 'Pck', true],
            ['Kaffee', 'Pck', false], ['Apfelsaft', 'l', false], ['Spülmittel', 'Fl', false],
            ['Klopapier', 'Pck', false], ['Zahnpasta', 'Stk', false],
            // Die Ausnahmen, die es nicht überall gibt -- siehe verfuegbarkeit()
            ['Misopaste', 'g', true], ['Reisessig', 'Fl', true], ['Sojasauce', 'Fl', true],
            ['Rohmilch', 'l', false],
        ];

        $categorizer = app(ProductCategorizer::class);

        foreach ($artikel as [$name, $einheit, $grundvorrat]) {
            $produkt = new Product([
                'name' => $name,
                'default_unit' => $einheit,
                'is_pantry_staple' => $grundvorrat,
            ]);

            $produkt->product_group_id = $categorizer->categorize($name)?->getKey();
            $produkt->save();
        }
    }

    /**
     * Der Grundvorrat (B-08) -- die Vorlage des Assistenten "Neue Woche" und
     * die Grundlage der Bestandsaufnahme.
     */
    protected function grundvorrat(): void
    {
        $sollmengen = [
            'Vollmilch' => [2, 'l'], 'Butter' => [1, 'Pck'], 'Vollkornbrot' => [1, 'Stk'],
            'Eier' => [10, 'Stk'], 'Kaffee' => [1, 'Pck'], 'Klopapier' => [1, 'Pck'],
        ];

        foreach ($sollmengen as $name => [$menge, $einheit]) {
            Product::matchingName($name)->first()?->update([
                'is_pantry_staple' => true,
                'pantry_target_amount' => $menge,
                'default_unit' => $einheit,
            ]);
        }
    }

    /**
     * Ein abgeschlossener Einkauf vor N Tagen.
     *
     * @param  array<int, string>  $eintraege
     * @param  array<int, string>  $offenLassen  Artikel, die es nicht gab -- sie
     *                                           wandern beim Abschluss auf die
     *                                           naechste Liste
     */
    protected function einkauf(
        ShoppingListManager $manager,
        ListArchiver $archiver,
        ItemTextParser $parser,
        User $owner,
        User $einkaeufer,
        int $vorTagen,
        array $eintraege,
        array $offenLassen = [],
    ): void {
        Carbon::setTestNow(now()->subDays($vorTagen)->setTime(9, 15));

        $liste = $manager->current($owner);
        $liste->forceFill(['name' => 'Einkauf '.now()->format('d.m.Y')])->save();

        foreach ($eintraege as $text) {
            $manager->addOrMerge($liste, $parser->parse($text));
        }

        Carbon::setTestNow(now()->setTime(17, 40));

        foreach ($liste->items()->get() as $item) {
            $fehlt = collect($offenLassen)->contains(
                fn (string $name) => str_contains(mb_strtolower($item->raw_name), mb_strtolower($name))
            );

            if (! $fehlt) {
                $manager->toggle($item, true, $einkaeufer);
            }
        }

        // Genau der Ablauf aus der Oberflaeche: archivieren und die fehlenden
        // Artikel auf die neue Liste schreiben.
        $archiver->archive($liste, true, $owner);

        Carbon::setTestNow();
    }

    /**
     * Die laufende Liste: teils erledigt, mit Notizen und einer manuell
     * umgehaengten Warengruppe.
     */
    protected function laufendeListe(ShoppingListManager $manager, ItemTextParser $parser, User $owner, User $partner): void
    {
        $liste = $manager->current($owner);

        $liste->forceFill([
            'name' => 'Wocheneinkauf '.now()->format('d.m.Y'),
            'market_id' => Market::where('is_default', true)->value('id'),
        ])->save();

        $texte = [
            '2 l Vollmilch', '1 Pck Butter', '10 Eier', 'Vollkornbrot', 'Naturjoghurt',
            '1 kg Kartoffeln', '2 Gurken', '1-2 Zwiebeln, rote', '500 g Hackfleisch',
            'Spaghetti', 'Olivenöl', 'Zahnpasta', 'Apfelsaft, 6 Flaschen',
            // Steht im Supermarkt unter "Gibt es hier nicht"
            '100 g Misopaste', 'Sojasauce',
        ];

        foreach ($texte as $text) {
            $manager->addOrMerge($liste, $parser->parse($text));
        }

        // Zwei Artikel liegen schon im Wagen -- abgehakt von verschiedenen
        // Mitgliedern, damit C-09 in der Oberflaeche etwas anzeigt.
        foreach (['Vollmilch', 'Vollkornbrot'] as $i => $name) {
            $item = ShoppingListItem::where('list_id', $liste->getKey())
                ->where('raw_name', $name)->first();

            if ($item !== null) {
                $manager->toggle($item, true, $i === 0 ? $owner : $partner);
            }
        }

        // Eine manuelle Umhaengung (C-05): Zahnpasta gehoert in dieser Familie
        // zur Drogerie, nicht dorthin, wo die Stichwortregeln sie ablegen.
        $zahnpasta = ShoppingListItem::where('list_id', $liste->getKey())
            ->where('raw_name', 'Zahnpasta')->first();

        if ($zahnpasta !== null) {
            $manager->moveToGroup($zahnpasta, ProductGroup::where('code', 'DROGERIE')->value('id'));
        }
    }

    /**
     * Zwei Vorlagen: eine fuer die Woche (als Standard, kommt also auf jede
     * neue Liste) und eine fuer den Anlass.
     */
    protected function vorlagen(ItemTextParser $parser): void
    {
        $applier = app(TemplateApplier::class);

        $vorlagen = [
            ['Wochenbedarf', 'Was ohnehin jede Woche gebraucht wird', true, [
                '2 l Vollmilch', '1 Pck Butter', '10 Eier', 'Vollkornbrot', '1 Pck Kaffee', '1 Pck Klopapier',
            ]],
            ['Grillabend', 'Für spontane Abende auf der Terrasse', false, [
                '1 kg Hackfleisch', '6 Brötchen', 'Gouda', '2 Gurken', '6 Flaschen Bier',
            ]],
        ];

        foreach ($vorlagen as [$name, $beschreibung, $istStandard, $texte]) {
            $vorlage = ListTemplate::create(['name' => $name, 'description' => $beschreibung]);

            foreach ($texte as $text) {
                $applier->position($vorlage, $parser->parse($text));
            }

            if ($istStandard) {
                $vorlage->makeDefault();
            }
        }
    }

    protected function bericht(Family $family): void
    {
        app(FamilyContext::class)->runFor($family->getKey(), function () {
            $aktiv = ShoppingList::active()->withCount('items')->first();

            $this->command?->info('Demodaten angelegt:');
            $this->command?->line('  Familie:      Familie Demo');
            $this->command?->line('  Anmeldung:    '.self::OWNER_EMAIL.' / '.self::PASSWORT);
            $this->command?->line('                partner@wocheneinkauf.test (Admin), kind@wocheneinkauf.test (Kind)');
            $this->command?->line('  Artikel:      '.Product::count().' im Katalog, '
                .Product::grundvorrat()->count().' als Grundvorrat');
            $this->command?->line('  Märkte:       '.Market::count().', '
                .Product::has('markets')->count().' Artikel mit eingeschränkter Verfügbarkeit');
            $this->command?->line('  Liste:        '.($aktiv?->items_count ?? 0).' Einträge');
            $this->command?->line('  Archiv:       '.ShoppingList::archived()->count().' abgeschlossene Einkäufe');
            $this->command?->line('  Vorlagen:     '.ListTemplate::count().', Standard: '
                .(ListTemplate::where('is_default', true)->value('name') ?? '—'));
        });
    }
}
