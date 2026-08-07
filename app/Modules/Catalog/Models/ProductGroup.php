<?php

namespace App\Modules\Catalog\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Markets\Models\MarketSection;
use Database\Factories\Catalog\ProductGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Warengruppe (B-01) -- ersetzt die fest verdrahtete CATEGORY_MAP der alten App.
 */
#[Fillable(['code', 'label', 'sort'])]
class ProductGroup extends Model
{
    /** @use HasFactory<ProductGroupFactory> */
    use BelongsToFamily, HasFactory;

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
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<ProductGroupRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(ProductGroupRule::class);
    }

    /**
     * Die Regalbereiche, in denen diese Warengruppe einsortiert ist (B-04).
     *
     * Die Pivot-Tabelle traegt family_id und ist ueber Verbund-Fremdschluessel
     * an beide Eltern gebunden -- die Datenbank laesst familienuebergreifende
     * Verknuepfungen gar nicht erst zu (R-03).
     *
     * @return BelongsToMany<MarketSection, $this>
     */
    public function marketSections(): BelongsToMany
    {
        return $this->belongsToMany(MarketSection::class, 'market_section_groups')
            ->withPivot('family_id');
    }
}
