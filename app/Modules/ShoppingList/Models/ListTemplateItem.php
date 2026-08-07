<?php

namespace App\Modules\ShoppingList\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductGroup;
use App\Modules\ShoppingList\Data\ParsedItem;
use Database\Factories\ShoppingList\ListTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Position einer Vorlage -- derselbe Satz Angaben wie ein Listeneintrag,
 * ohne alles, was zum Einkaufen selbst gehoert (Haken, Sortierung im Markt,
 * Sync-Kennung).
 */
#[Fillable(['raw_name', 'amount', 'amount_raw', 'unit', 'note', 'product_id', 'product_group_id', 'sort'])]
class ListTemplateItem extends Model
{
    /** @use HasFactory<ListTemplateItemFactory> */
    use BelongsToFamily, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:3',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ListTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ListTemplate::class, 'template_id');
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
     * Als Eingabe fuer die Einkaufsliste.
     */
    public function alsEingabe(): ParsedItem
    {
        return new ParsedItem(
            name: $this->raw_name,
            amount: $this->amount === null ? null : (float) $this->amount,
            amountRaw: $this->amount_raw,
            unit: $this->unit,
            note: $this->note,
        );
    }

    /**
     * Menge und Einheit als ein Stueck Text -- wie auf der Liste.
     */
    public function mengeAnzeige(): string
    {
        $menge = $this->amount_raw ?? ShoppingListItem::formatiereMenge($this->amount);

        return trim($menge.' '.($this->unit ?? ''));
    }

    public function bearbeitungstext(): string
    {
        return trim($this->mengeAnzeige().' '.$this->raw_name);
    }
}
