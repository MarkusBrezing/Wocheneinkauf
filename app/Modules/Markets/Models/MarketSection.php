<?php

namespace App\Modules\Markets\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Catalog\Models\ProductGroup;
use Database\Factories\Markets\MarketSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Regalbereich eines Marktes (B-03), in Laufreihenfolge sortiert.
 */
#[Fillable(['name', 'sort'])]
class MarketSection extends Model
{
    /** @use HasFactory<MarketSectionFactory> */
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
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * Die Warengruppen, die dieser Bereich buendelt (B-04).
     *
     * @return BelongsToMany<ProductGroup, $this>
     */
    public function productGroups(): BelongsToMany
    {
        return $this->belongsToMany(ProductGroup::class, 'market_section_groups')
            ->withPivot('family_id');
    }
}
