<?php

namespace App\Providers;

use App\Core\Support\FamilyContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Ein Kontext pro Request: der Global Scope auf BelongsToFamily-Models
        // und die Controller muessen dieselbe Familie sehen.
        $this->app->singleton(FamilyContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Die Models liegen nach Modulen sortiert, nicht im Standardordner
        // app/Models. Ohne diese Zuordnung sucht Eloquent die Factory unter
        // Database\Factories\Core\Models\UserFactory und findet sie nicht.
        //
        //   App\Core\Models\User             -> Database\Factories\Core\UserFactory
        //   App\Modules\Catalog\Models\Item  -> Database\Factories\Catalog\ItemFactory
        //
        // Der Modulname bleibt im Pfad, damit gleichnamige Models aus
        // verschiedenen Modulen sich nicht in die Quere kommen.
        Factory::guessFactoryNamesUsing(function (string $modelName) {
            $module = preg_replace(
                ['/^App\\\\Core\\\\Models\\\\.*$/', '/^App\\\\Modules\\\\([^\\\\]+)\\\\Models\\\\.*$/'],
                ['Core', '$1'],
                $modelName
            );

            if ($module === $modelName) {
                return 'Database\\Factories\\'.class_basename($modelName).'Factory';
            }

            return 'Database\\Factories\\'.$module.'\\'.class_basename($modelName).'Factory';
        });

        // Pflichtangaben in Impressum und Datenschutzerklaerung. Fehlt eine,
        // steht dort eine sichtbare Luecke statt einer stillen leeren Zeile --
        // ein unvollstaendiges Impressum ist abmahnfaehig und soll deshalb
        // beim Draufschauen auffallen.
        Blade::directive('angabe', function (string $ausdruck) {
            return "<?php echo \\App\\Core\\Support\\Rechtstext::angabe({$ausdruck}); ?>";
        });
    }
}
