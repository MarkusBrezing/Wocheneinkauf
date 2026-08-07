<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\HandbuchController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ListTemplateController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\MealPlanController;
use App\Http\Controllers\NewWeekController;
use App\Http\Controllers\PantryController;
use App\Http\Controllers\PantryInventoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductGroupController;
use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeIngredientController;
use App\Http\Controllers\RecipeToListController;
use App\Http\Controllers\ShoppingListArchiveController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingListSyncController;
use App\Http\Controllers\ShoppingModeController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
});

/*
 * Health-Check (G-01): DB erreichbar, Version, Migrationsstand.
 * Grundlage fuer den Smoke-Test nach jedem Deployment.
 */
Route::get('/health', function () {
    $db = 'down';
    $migrationen = null;

    try {
        DB::connection()->getPdo();
        $db = 'up';

        $migrator = app('migrator');
        $ausgefuehrt = $migrator->getRepository()->getRan();
        $vorhanden = array_keys($migrator->getMigrationFiles([database_path('migrations')]));

        $migrationen = [
            'ausgefuehrt' => count($ausgefuehrt),
            'offen' => count(array_diff($vorhanden, $ausgefuehrt)),
            'letzte' => empty($ausgefuehrt) ? null : end($ausgefuehrt),
        ];
    } catch (Throwable $e) {
        $db = 'down';
    }

    // Offene Migrationen bedeuten: der Code ist neuer als das Schema. Das ist
    // kein Ausfall, aber ein Deployment, das nur halb durchgelaufen ist.
    $gesund = $db === 'up' && ($migrationen['offen'] ?? 0) === 0;

    return response()->json([
        'status' => $gesund ? 'ok' : 'degraded',
        'db' => $db,
        'migrationen' => $migrationen,
        'app' => config('app.name'),
        'laravel' => app()->version(),
        'php' => PHP_VERSION,
        'time' => now()->toIso8601String(),
    ], $gesund ? 200 : 503);
});

/*
 * Rechtstexte. Ohne Anmeldung erreichbar -- ein Impressum hinter einem Login
 * ist keines.
 */
Route::get('/impressum', [LegalController::class, 'impressum'])->name('impressum');
Route::get('/datenschutz', [LegalController::class, 'datenschutz'])->name('datenschutz');
Route::get('/nutzungsbedingungen', [LegalController::class, 'nutzungsbedingungen'])->name('nutzungsbedingungen');

// Das Web-App-Manifest (Schritt 9). Als Route, damit der Medientyp stimmt.
Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

/*
 * Anwenderhandbuch. Ebenfalls ohne Anmeldung -- die haeufigste Frage kommt von
 * jemandem, der gerade nicht hineinkommt. Die feste Route "komplett" steht vor
 * dem Platzhalter, sonst wuerde sie als Kapitelname gelesen.
 */
Route::get('/handbuch', [HandbuchController::class, 'index'])->name('handbuch.index');
Route::get('/handbuch/komplett', [HandbuchController::class, 'komplett'])->name('handbuch.komplett');
Route::get('/handbuch/{kapitel}', [HandbuchController::class, 'show'])->name('handbuch.show');

/*
 * Einladungen werden auch von Gaesten geoeffnet -- wer eingeladen wird, hat in
 * der Regel noch kein Konto (A-07).
 */
Route::get('/einladung/{token}', [InvitationController::class, 'show'])->name('invitation.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::post('/einladung/{token}', [InvitationController::class, 'accept'])->name('invitation.accept');

    // Familie: Einstellungen (A-10), Mitglieder (A-08), Wechsel (A-13)
    Route::get('/familie', [FamilyController::class, 'edit'])->name('family.edit');
    Route::put('/familie', [FamilyController::class, 'update'])->name('family.update');
    Route::post('/familie/wechseln', [FamilyController::class, 'switch'])->name('family.switch');
    Route::patch('/familie/mitglieder/{mitglied}', [FamilyController::class, 'updateMember'])->name('family.members.update');
    Route::delete('/familie/mitglieder/{mitglied}', [FamilyController::class, 'destroyMember'])->name('family.members.destroy');

    // Einladungen (A-06, A-09)
    Route::post('/familie/einladungen', [InvitationController::class, 'store'])->name('invitations.store');
    Route::delete('/familie/einladungen/{einladung}', [InvitationController::class, 'destroy'])->name('invitations.destroy');

    /*
     * Einkaufsliste (C-01 … C-17). Eintraege werden ueber ihre client_uuid
     * adressiert -- dieselbe Kennung, mit der der Sync arbeitet.
     */
    Route::prefix('einkaufsliste')->name('einkaufsliste.')->group(function () {
        Route::get('/', [ShoppingListController::class, 'index'])->name('index');
        Route::post('/eintraege', [ShoppingListController::class, 'store'])->name('eintraege.store');
        Route::put('/eintraege/{eintrag}', [ShoppingListController::class, 'update'])->name('eintraege.update');
        Route::post('/eintraege/{eintrag}/haken', [ShoppingListController::class, 'toggle'])->name('eintraege.haken');
        Route::delete('/eintraege/{eintrag}', [ShoppingListController::class, 'destroy'])->name('eintraege.destroy');
        Route::post('/markt', [ShoppingListController::class, 'switchMarket'])->name('markt');
        Route::get('/drucken', [ShoppingListController::class, 'print'])->name('drucken');

        // Die Einkaufsansicht fuers Telefon: nur abhaken und beenden.
        Route::get('/einkaufen', [ShoppingModeController::class, 'show'])->name('einkaufen');

        // Einkauf abschliessen: archivieren und fehlende Artikel uebernehmen
        Route::post('/archivieren', [ShoppingListController::class, 'archive'])->name('archivieren');

        // Assistent "Neue Woche" (C-10)
        Route::get('/neue-woche', [NewWeekController::class, 'show'])->name('neue-woche');
        Route::post('/neue-woche', [NewWeekController::class, 'start'])->name('neue-woche.start');

        // Bestandsaufnahme des Grundvorrats: Fehlmengen auf die Liste
        Route::get('/inventur', [PantryInventoryController::class, 'show'])->name('inventur');
        Route::post('/inventur', [PantryInventoryController::class, 'store'])->name('inventur.store');

        // Archiv (C-12, C-13, C-14)
        Route::get('/archiv', [ShoppingListArchiveController::class, 'index'])->name('archiv.index');
        Route::get('/archiv/{liste}', [ShoppingListArchiveController::class, 'show'])->name('archiv.show');
        Route::post('/archiv/{liste}/laden', [ShoppingListArchiveController::class, 'load'])->name('archiv.laden');
        Route::delete('/archiv/{liste}', [ShoppingListArchiveController::class, 'destroy'])->name('archiv.destroy');
    });

    /*
     * Vorrat und Inventur (E-01 … E-11).
     */
    Route::prefix('vorrat')->name('vorrat.')->group(function () {
        Route::get('/', [PantryController::class, 'index'])->name('index');
        Route::post('/', [PantryController::class, 'store'])->name('store');
        Route::put('/{position}', [PantryController::class, 'update'])->name('update');
        Route::delete('/{position}', [PantryController::class, 'destroy'])->name('destroy');
        Route::post('/{position}/buchen', [PantryController::class, 'book'])->name('buchen');
        Route::get('/{position}/verlauf', [PantryController::class, 'movements'])->name('verlauf');
        Route::post('/auf-die-liste', [PantryController::class, 'toList'])->name('auf-die-liste');

        // Inventurlauf (E-03 … E-10)
        Route::get('/inventur/uebersicht', [InventoryController::class, 'index'])->name('inventur.index');
        Route::post('/inventur', [InventoryController::class, 'store'])->name('inventur.store');
        Route::get('/inventur/{inventur}', [InventoryController::class, 'show'])->name('inventur.show');
        Route::put('/inventur/zeile/{zeile}', [InventoryController::class, 'count'])->name('inventur.zaehlen');
        Route::post('/inventur/{inventur}/abschliessen', [InventoryController::class, 'complete'])->name('inventur.abschliessen');
        Route::post('/inventur/{inventur}/abbrechen', [InventoryController::class, 'cancel'])->name('inventur.abbrechen');
        Route::post('/inventur/{inventur}/auf-die-liste', [InventoryController::class, 'toList'])->name('inventur.auf-die-liste');
    });

    /*
     * Wochenplan (F-01 … F-15). Slots und Vorschlaege werden ueber ihre ID
     * gebunden -- der Global Scope laeuft mit, ein fremder Plan endet in 404.
     */
    Route::prefix('wochenplan')->name('wochenplan.')->group(function () {
        Route::get('/', [MealPlanController::class, 'index'])->name('index');
        Route::get('/historie', [MealPlanController::class, 'history'])->name('historie');
        Route::get('/drucken', [MealPlanController::class, 'print'])->name('drucken');
        Route::put('/slots/{slot}', [MealPlanController::class, 'fill'])->name('slots.fill');
        Route::delete('/slots/{slot}', [MealPlanController::class, 'clear'])->name('slots.clear');
        Route::post('/slots/{slot}/sperren', [MealPlanController::class, 'lock'])->name('slots.sperren');
        Route::post('/slots/{slot}/gekocht', [MealPlanController::class, 'cooked'])->name('slots.gekocht');
        Route::post('/slots/{slot}/wuerfeln', [MealPlanController::class, 'reroll'])->name('slots.wuerfeln');

        Route::post('/{plan}/vorschlag', [MealPlanController::class, 'suggest'])->name('vorschlag');
        Route::post('/vorschlag/{vorschlag}/annehmen', [MealPlanController::class, 'accept'])->name('vorschlag.annehmen');
        Route::post('/vorschlag/{vorschlag}/verwerfen', [MealPlanController::class, 'discard'])->name('vorschlag.verwerfen');

        Route::post('/{plan}/auf-die-liste', [MealPlanController::class, 'toList'])->name('auf-die-liste');
        Route::post('/{plan}/kopieren', [MealPlanController::class, 'copy'])->name('kopieren');
        Route::post('/rezepte/{rezept}/ausschliessen', [MealPlanController::class, 'exclude'])->name('rezept.ausschliessen');
    });

    /*
     * Rezepte (D-01 … D-08).
     */
    Route::prefix('rezepte')->name('rezepte.')->group(function () {
        Route::get('/', [RecipeController::class, 'index'])->name('index');
        Route::get('/neu', [RecipeController::class, 'create'])->name('create');
        Route::post('/', [RecipeController::class, 'store'])->name('store');
        Route::get('/{rezept}', [RecipeController::class, 'show'])->name('show');
        Route::get('/{rezept}/bearbeiten', [RecipeController::class, 'edit'])->name('edit');
        Route::put('/{rezept}', [RecipeController::class, 'update'])->name('update');
        Route::delete('/{rezept}', [RecipeController::class, 'destroy'])->name('destroy');
        Route::post('/{rezept}/favorit', [RecipeController::class, 'toggleFavorite'])->name('favorit');
        Route::put('/{rezept}/schlagworte', [RecipeController::class, 'syncTags'])->name('schlagworte');

        // Zutaten (D-03, D-08)
        Route::post('/{rezept}/zutaten', [RecipeIngredientController::class, 'store'])->name('zutaten.store');
        Route::put('/zutaten/{zutat}', [RecipeIngredientController::class, 'update'])->name('zutaten.update');
        Route::delete('/zutaten/{zutat}', [RecipeIngredientController::class, 'destroy'])->name('zutaten.destroy');

        // Auf die Einkaufsliste (D-04, D-05, D-06)
        Route::post('/{rezept}/auf-die-liste', [RecipeToListController::class, 'store'])->name('auf-die-liste');
    });

    /*
     * Vorlagen fuer neue Einkaufslisten. Route-Model-Binding laeuft durch den
     * Global Scope -- eine fremde Vorlage endet in 404.
     */
    Route::prefix('vorlagen')->name('vorlagen.')->group(function () {
        Route::get('/', [ListTemplateController::class, 'index'])->name('index');
        Route::post('/', [ListTemplateController::class, 'store'])->name('store');
        Route::post('/aus-liste', [ListTemplateController::class, 'fromList'])->name('aus-liste');
        Route::get('/{vorlage}', [ListTemplateController::class, 'show'])->name('show');
        Route::put('/{vorlage}', [ListTemplateController::class, 'update'])->name('update');
        Route::delete('/{vorlage}', [ListTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/{vorlage}/standard', [ListTemplateController::class, 'makeDefault'])->name('standard');
        Route::post('/{vorlage}/anwenden', [ListTemplateController::class, 'apply'])->name('anwenden');
        Route::post('/{vorlage}/positionen', [ListTemplateController::class, 'storeItem'])->name('positionen.store');
        Route::put('/positionen/{position}', [ListTemplateController::class, 'updateItem'])->name('positionen.update');
        Route::delete('/positionen/{position}', [ListTemplateController::class, 'destroyItem'])->name('positionen.destroy');
    });

    /*
     * Delta-Sync fuers Offline-Frontend (C-07, C-08).
     *
     * Bewusst hinter der normalen Sitzung samt CSRF-Schutz statt hinter einem
     * zweiten Tokenverfahren -- das Frontend laeuft auf derselben Domain. Der
     * Pfad beginnt trotzdem mit api/, weil bootstrap/app.php Fehlerantworten
     * genau dort als JSON rendert und ueberall sonst als Weiterleitung. Ein
     * Sync-Client kann mit einer Weiterleitung nichts anfangen.
     */
    Route::prefix('api/einkaufsliste')->name('einkaufsliste.sync.')->group(function () {
        Route::get('/stand', [ShoppingListSyncController::class, 'show'])->name('show');
        Route::post('/stand', [ShoppingListSyncController::class, 'store'])->name('store');
    });

    /*
     * Stammdaten (B-01 … B-08). Die Route-Model-Bindings greifen auf Models
     * mit BelongsToFamily zu -- der Global Scope laeuft dabei mit, ein
     * Datensatz einer fremden Familie endet also in 404.
     */
    Route::prefix('stammdaten')->name('stammdaten.')->group(function () {
        // Warengruppen (B-01)
        Route::get('/warengruppen', [ProductGroupController::class, 'index'])->name('warengruppen.index');
        Route::post('/warengruppen', [ProductGroupController::class, 'store'])->name('warengruppen.store');
        Route::put('/warengruppen/{warengruppe}', [ProductGroupController::class, 'update'])->name('warengruppen.update');
        Route::delete('/warengruppen/{warengruppe}', [ProductGroupController::class, 'destroy'])->name('warengruppen.destroy');

        // Maerkte (B-02, B-05) und Regalbereiche (B-03, B-04)
        Route::get('/maerkte', [MarketController::class, 'index'])->name('maerkte.index');
        Route::post('/maerkte', [MarketController::class, 'store'])->name('maerkte.store');
        Route::put('/maerkte/{markt}', [MarketController::class, 'update'])->name('maerkte.update');
        Route::delete('/maerkte/{markt}', [MarketController::class, 'destroy'])->name('maerkte.destroy');
        Route::post('/maerkte/{markt}/standard', [MarketController::class, 'makeDefault'])->name('maerkte.standard');
        Route::post('/maerkte/{markt}/bereiche', [MarketController::class, 'storeSection'])->name('bereiche.store');
        Route::put('/bereiche/{bereich}', [MarketController::class, 'updateSection'])->name('bereiche.update');
        Route::delete('/bereiche/{bereich}', [MarketController::class, 'destroySection'])->name('bereiche.destroy');
        Route::put('/bereiche/{bereich}/warengruppen', [MarketController::class, 'syncGroups'])->name('bereiche.gruppen');

        // Artikelkatalog (B-06)
        Route::get('/produkte', [ProductController::class, 'index'])->name('produkte.index');
        Route::post('/produkte', [ProductController::class, 'store'])->name('produkte.store');
        Route::put('/produkte/{produkt}', [ProductController::class, 'update'])->name('produkte.update');
        Route::delete('/produkte/{produkt}', [ProductController::class, 'destroy'])->name('produkte.destroy');
        Route::post('/produkte/zusammenfuehren', [ProductController::class, 'merge'])->name('produkte.merge');

    });

    // Konto: Auskunft und Loeschung (A-11, A-12)
    Route::get('/konto', [AccountController::class, 'edit'])->name('account.edit');
    Route::get('/konto/auskunft', [AccountController::class, 'export'])->name('account.export');
    Route::delete('/konto', [AccountController::class, 'destroy'])->name('account.destroy');
    Route::delete('/konto/familie', [AccountController::class, 'destroyFamily'])->name('account.family.destroy');
});
