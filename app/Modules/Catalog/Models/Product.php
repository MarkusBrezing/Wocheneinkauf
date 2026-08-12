<?php

namespace App\Modules\Catalog\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Markets\Models\Market;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Database\Factories\Catalog\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Artikelkatalog (B-06): die gemeinsame Klammer fuer Listeneintrag,
 * Rezeptzutat und Vorratsposition.
 */
#[Fillable(['name', 'product_group_id', 'default_unit', 'is_pantry_staple', 'pantry_target_amount'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToFamily, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // name_normalized ist die Grundlage der Dublettenerkennung und darf
        // nie von Hand gesetzt werden.
        static::saving(function (Product $product) {
            if ($product->isDirty('name')) {
                $product->name_normalized = static::normalize($product->name);
            }
        });

        // Konvention aus Review-Befund R-04: Beim Soft Delete bekommt
        // name_normalized das Suffix ~{id}. Ohne das blockiert der geloeschte
        // Datensatz den UNIQUE-Key (family_id, name_normalized) dauerhaft --
        // derselbe Artikelname liesse sich nie wieder anlegen.
        static::deleting(function (Product $product) {
            if ($product->isForceDeleting() || str_contains($product->name_normalized, '~')) {
                return;
            }

            $product->forceFill([
                'name_normalized' => $product->name_normalized.'~'.$product->getKey(),
            ])->saveQuietly();
        });

        // Beim Wiederherstellen faellt das Suffix weg.
        static::restored(function (Product $product) {
            $product->forceFill([
                'name_normalized' => static::normalize($product->name),
            ])->saveQuietly();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pantry_staple' => 'boolean',
            'pantry_target_amount' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /**
     * Die Maerkte, in denen es diesen Artikel gibt.
     *
     * Leer heisst nicht "nirgends", sondern "ueberall": eingetragen wird nur
     * die Ausnahme. Tomaten bekommt man an jeder Ecke, Misopaste nur im
     * Asialaden -- und nur Letzteres muss jemand pflegen.
     *
     * @return BelongsToMany<Market, $this>
     */
    public function markets(): BelongsToMany
    {
        return $this->belongsToMany(Market::class)
            ->withPivot('family_id')
            ->orderBy('markets.sort')
            ->orderBy('markets.name');
    }

    /**
     * Gibt es den Artikel in diesem Markt?
     *
     * Ohne Einschraenkung immer ja. Ohne Markt ebenso -- wer nach
     * Warengruppen sortiert, kauft nicht in einem bestimmten Laden ein.
     */
    public function istErhaeltlichIn(Market|int|null $markt): bool
    {
        $maerkte = $this->relationLoaded('markets') ? $this->markets : $this->markets()->get();

        if ($maerkte->isEmpty() || $markt === null) {
            return true;
        }

        $marktId = $markt instanceof Market ? $markt->getKey() : $markt;

        return $maerkte->contains(fn (Market $m) => $m->getKey() === $marktId);
    }

    public function istUeberallErhaeltlich(): bool
    {
        return ($this->relationLoaded('markets') ? $this->markets : $this->markets()->get())->isEmpty();
    }

    /**
     * "nur bei Asialaden" -- oder null, wenn es den Artikel ueberall gibt.
     */
    public function verfuegbarkeitshinweis(): ?string
    {
        $maerkte = $this->relationLoaded('markets') ? $this->markets : $this->markets()->get();

        if ($maerkte->isEmpty()) {
            return null;
        }

        return 'nur bei '.$maerkte->pluck('name')->join(', ', ' und ');
    }

    /**
     * Der Grundvorrat: was im Haus sein soll (B-08).
     *
     * Fruehere Fassungen fuehrten das doppelt -- als "Fixartikel" in einer
     * eigenen Tabelle und als Kennzeichen am Artikel. Es war beides dasselbe:
     * Milch, Butter, Mehl, Klopapier. Seit der Zusammenfuehrung entscheidet
     * allein dieses Kennzeichen, und pantry_target_amount sagt, wie viel davon
     * da sein soll.
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeGrundvorrat(Builder $query): Builder
    {
        return $query->where('is_pantry_staple', true);
    }

    /**
     * Sollmenge und Einheit als ein Stueck Text: "2 l", "10 Stk".
     */
    /**
     * Die Sollmenge als Wert fuer ein Zahlenfeld.
     *
     * decimal(10,3) liefert "2.000" -- in einer Pflegetabelle mit hundert
     * Zeilen sind die Nachkommanullen nur Rauschen. Anders als
     * sollmengeAnzeige() bleibt hier der Punkt als Trennzeichen stehen: Ein
     * input[type=number] versteht kein Komma und wuerde das Feld leeren.
     */
    public function sollmengeFeldwert(): string
    {
        if ($this->pantry_target_amount === null) {
            return '';
        }

        $wert = rtrim(rtrim((string) $this->pantry_target_amount, '0'), '.');

        return $wert === '' ? '0' : $wert;
    }

    public function sollmengeAnzeige(): string
    {
        $menge = ShoppingListItem::formatiereMenge($this->pantry_target_amount);

        return trim($menge.' '.($this->default_unit ?? ''));
    }

    /**
     * Vergleichsform eines Artikelnamens: klein, ohne Klammerzusatz, ohne
     * doppelte Leerzeichen. "Tomaten (Bio)" und "tomaten" sind damit derselbe
     * Artikel -- genau das leistete frueher das Zeichenketten-Gefummel in
     * sameIngredient() und nameVariants().
     */
    public static function normalize(string $name): string
    {
        $ohneKlammern = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;

        return Str::squish(Str::lower($ohneKlammern));
    }

    /**
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeMatchingName(Builder $query, string $name): Builder
    {
        return $query->where('name_normalized', static::normalize($name));
    }
}
