<?php

namespace App\Modules\ShoppingList\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\Markets\Models\Market;
use Database\Factories\ShoppingList\ShoppingListItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Ein Eintrag auf der Einkaufsliste.
 *
 * Soft Delete ist hier keine Vorsichtsmassnahme, sondern Voraussetzung fuer
 * den Sync: eine ersatzlos verschwundene Zeile laesst sich im Delta nicht von
 * einer unterscheiden, die es nie gab (C-08).
 */
#[Fillable([
    'raw_name', 'amount', 'amount_raw', 'unit', 'note',
    'product_id', 'product_group_id', 'source', 'source_ref_id', 'sort',
])]
class ShoppingListItem extends Model
{
    /** @use HasFactory<ShoppingListItemFactory> */
    use BelongsToFamily, HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        // Im Browser erzeugt der Client die UUID selbst und macht seinen POST
        // damit wiederholbar. Kommt der Eintrag aus einem Formular oder einem
        // Assistenten, gibt es keinen Client -- dann vergibt sie der Server.
        static::creating(function (ShoppingListItem $item) {
            $item->client_uuid ??= (string) Str::uuid();
        });

        // Die Version der Liste haengt an *jeder* Aenderung ihrer Eintraege,
        // sonst verpasst ein anderes Geraet sie (C-07). Das hier zu verankern
        // statt in den Aufrufern ist der Unterschied zwischen einer Garantie
        // und einer Absichtserklaerung: der naechste Dienst, der Eintraege
        // schreibt -- Rezepte, Wochenplan -- bekommt sie geschenkt.
        $bump = fn (ShoppingListItem $item) => ShoppingList::bumpVersionFor($item->list_id);

        static::saved($bump);
        static::deleted($bump);
        static::restored($bump);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'is_checked' => 'boolean',
            'checked_at' => 'datetime',
            'sort' => 'integer',
        ];
    }

    /**
     * Nach aussen adressiert die client_uuid den Eintrag -- dieselbe Kennung,
     * die auch der Sync verwendet. Damit gibt es fuer Formular und API einen
     * Bezeichner statt zweier, und fortlaufende Schluessel bleiben innen.
     */
    public function getRouteKeyName(): string
    {
        return 'client_uuid';
    }

    /**
     * @return BelongsTo<ShoppingList, $this>
     */
    public function list(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class, 'list_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }

    /**
     * Wer den Artikel abgehakt hat (C-09).
     *
     * @return BelongsTo<User, $this>
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * @param  Builder<ShoppingListItem>  $query
     * @return Builder<ShoppingListItem>
     */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->where('is_checked', false);
    }

    /**
     * Die Vergleichsform des Namens -- dieselbe wie im Artikelkatalog, damit
     * "Tomaten (Bio)" und "tomaten" auch auf der Liste derselbe Eintrag sind
     * (C-17).
     */
    public function vergleichsname(): string
    {
        return Product::normalize($this->raw_name);
    }

    /**
     * Gibt es den Artikel im gewaehlten Markt?
     *
     * Ein Eintrag ohne Katalogverknuepfung -- reiner Freitext -- gilt als
     * ueberall erhaeltlich. Ueber ihn ist schlicht nichts bekannt, und eine
     * Warnung ins Blaue hinein waere schlimmer als keine.
     */
    public function istErhaeltlichIn(Market|int|null $markt): bool
    {
        return $this->product?->istErhaeltlichIn($markt) ?? true;
    }

    /**
     * "nur bei Asialaden", oder null wenn es den Artikel ueberall gibt.
     */
    public function verfuegbarkeitshinweis(): ?string
    {
        return $this->product?->verfuegbarkeitshinweis();
    }

    /**
     * Menge und Einheit als ein Stueck Text: "2 l", "1-2", "500 g".
     */
    public function mengeAnzeige(): string
    {
        $menge = $this->amount_raw ?? static::formatiereMenge($this->amount);

        return trim($menge.' '.($this->unit ?? ''));
    }

    /**
     * Menge, Einheit und Name in einer Zeile -- so, wie der Eintrag getippt
     * worden waere. Der Parser liest sie beim Bearbeiten wieder ein (C-03),
     * damit es fuer Menge und Name nur ein Eingabefeld braucht.
     */
    public function bearbeitungstext(): string
    {
        return trim($this->mengeAnzeige().' '.$this->raw_name);
    }

    /**
     * 2.000 -> "2", 0.500 -> "0,5". Nachkommanullen sind Rauschen auf einem
     * Zettel, den man im Supermarkt liest.
     */
    public static function formatiereMenge(int|float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $zahl = (float) $amount;

        if ($zahl <= 0.0) {
            return '';
        }

        return rtrim(rtrim(number_format($zahl, 3, ',', ''), '0'), ',');
    }
}
