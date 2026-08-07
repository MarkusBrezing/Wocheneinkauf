<?php

namespace App\Modules\Pantry\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Catalog\Models\Product;
use App\Modules\ShoppingList\Models\ShoppingListItem;
use Database\Factories\Pantry\PantryItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Vorratsposition (E-01): ein Artikel an einem Ort, mit Soll, Ist und
 * Meldebestand.
 *
 * Derselbe Artikel darf mehrfach vorkommen -- Nudeln in der Speisekammer und
 * im Keller sind zwei Positionen mit eigenem Bestand. Deshalb bildet erst das
 * Paar (Artikel, Ort) den eindeutigen Schluessel.
 */
#[Fillable(['location', 'unit', 'target_qty', 'min_qty', 'best_before', 'is_active', 'sort'])]
class PantryItem extends Model
{
    /** @use HasFactory<PantryItemFactory> */
    use BelongsToFamily, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_qty' => 'decimal:3',
            'min_qty' => 'decimal:3',
            'current_qty' => 'decimal:3',
            'best_before' => 'date',
            'is_active' => 'boolean',
            'sort' => 'integer',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<PantryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(PantryMovement::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @param  Builder<PantryItem>  $query
     * @return Builder<PantryItem>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Positionen unter dem Meldebestand (E-11).
     *
     * @param  Builder<PantryItem>  $query
     * @return Builder<PantryItem>
     */
    public function scopeUnterMeldebestand(Builder $query): Builder
    {
        return $query->whereColumn('current_qty', '<=', 'min_qty')->where('min_qty', '>', 0);
    }

    /**
     * Was zum Soll fehlt -- die Menge, die auf die Einkaufsliste gehoert
     * (E-09). Null, wenn nichts fehlt.
     */
    public function fehlmenge(): ?float
    {
        $fehlt = round((float) $this->target_qty - (float) $this->current_qty, 3);

        return $fehlt > 0.0 ? $fehlt : null;
    }

    public function istKnapp(): bool
    {
        return (float) $this->min_qty > 0 && (float) $this->current_qty <= (float) $this->min_qty;
    }

    public function laeuftAb(int $tage = 7): bool
    {
        return $this->best_before !== null && $this->best_before->lessThanOrEqualTo(now()->addDays($tage));
    }

    public function bestandAnzeige(): string
    {
        return trim(ShoppingListItem::formatiereMenge($this->current_qty).' '.($this->unit ?? ''));
    }

    public function sollAnzeige(): string
    {
        return trim(ShoppingListItem::formatiereMenge($this->target_qty).' '.($this->unit ?? ''));
    }
}
