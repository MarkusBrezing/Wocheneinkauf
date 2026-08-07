<?php

namespace App\Modules\Recipes\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Data\ParsedItem;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Database\Factories\Recipes\RecipeIngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zutat eines Rezepts.
 *
 * raw_name ist der Originaltext ("Olivenöl"), buy_label das, was auf der
 * Einkaufsliste stehen soll, wenn es davon abweicht ("Olivenöl, gutes fürs
 * Dressing"). merge_key klammert Zutaten, die aus mehreren Rezepten
 * zusammenfallen sollen (D-06).
 */
#[Fillable([
    'raw_name', 'amount', 'unit', 'is_pantry', 'buy_label',
    'merge_key', 'is_optional', 'sort', 'product_id',
])]
class RecipeIngredient extends Model
{
    /** @use HasFactory<RecipeIngredientFactory> */
    use BelongsToFamily, HasFactory;

    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'is_pantry' => 'boolean',
            'is_optional' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * Die Verknuepfung in den Artikelkatalog (D-08) -- erst sie macht aus
     * einer Zeichenkette einen Artikel, den auch Vorrat und Liste kennen.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Der Name, unter dem die Zutat eingekauft wird.
     */
    public function einkaufsname(): string
    {
        return $this->buy_label ?: $this->raw_name;
    }

    /**
     * Als Eingabe fuer die Einkaufsliste, skaliert auf die gewuenschten
     * Portionen (D-04).
     *
     * @param  float  $faktor  gewuenschte Portionen / Portionen des Rezepts
     */
    public function alsEingabe(float $faktor = 1.0): ParsedItem
    {
        $menge = $this->amount === null ? null : round((float) $this->amount * $faktor, 3);

        return new ParsedItem(
            name: $this->einkaufsname(),
            amount: $menge,
            unit: $this->unit,
        );
    }

    public function mengeAnzeige(float $faktor = 1.0): string
    {
        $menge = $this->amount === null
            ? ''
            : ShoppingListItem::formatiereMenge(round((float) $this->amount * $faktor, 3));

        return trim($menge.' '.($this->unit ?? ''));
    }

    /**
     * Die Klammer, unter der Zutaten aus mehreren Rezepten zusammenfallen
     * (D-06). Ohne ausdrueckliche Angabe ist das die Normalform des
     * Einkaufsnamens -- dieselbe Regel wie im Artikelkatalog.
     */
    public function zusammenfassungsschluessel(): string
    {
        return $this->merge_key !== null && $this->merge_key !== ''
            ? mb_strtolower($this->merge_key)
            : Product::normalize($this->einkaufsname());
    }
}
