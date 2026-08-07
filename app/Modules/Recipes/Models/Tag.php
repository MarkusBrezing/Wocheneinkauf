<?php

namespace App\Modules\Recipes\Models;

use App\Core\Concerns\BelongsToFamily;
use Database\Factories\Recipes\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Ein Schlagwort fuer die Rezeptfilter (D-01).
 *
 * Der Typ bestimmt, in welcher Gruppe der Filter erscheint -- und spaeter,
 * welche Regel des Wochenplan-Generators ihn liest: "kein Rezept mit demselben
 * Protein-Tag an zwei Tagen hintereinander" (F-06).
 */
#[Fillable(['type', 'name'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToFamily, HasFactory;

    public $timestamps = false;

    /**
     * @var array<string, string>
     */
    public const TYPEN = [
        'protein' => 'Protein',
        'cuisine' => 'Küche',
        'diet' => 'Ernährung',
        'course' => 'Gang',
        'season' => 'Saison',
        'other' => 'Sonstiges',
    ];

    /**
     * @return BelongsToMany<Recipe, $this>
     */
    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'recipe_tag')->withPivot('family_id');
    }

    public function typLabel(): string
    {
        return self::TYPEN[$this->type] ?? $this->type;
    }
}
