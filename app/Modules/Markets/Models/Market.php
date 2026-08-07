<?php

namespace App\Modules\Markets\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Modules\Catalog\Models\Product;
use Database\Factories\Markets\MarketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Markt einer Familie (B-02, B-05).
 */
#[Fillable(['name', 'sort', 'is_active'])]
class Market extends Model
{
    /** @use HasFactory<MarketFactory> */
    use BelongsToFamily, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * Regalbereiche in Laufreihenfolge (B-03).
     *
     * @return HasMany<MarketSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(MarketSection::class)->orderBy('sort')->orderBy('name');
    }

    /**
     * Die Artikel, die es ausdruecklich nur in diesem (und ggf. weiteren)
     * Markt gibt. Alles, was hier nicht steht, ist ueberall erhaeltlich --
     * siehe Product::markets().
     *
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)->withPivot('family_id');
    }

    /**
     * @param  Builder<Market>  $query
     * @return Builder<Market>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Diesen Markt zum Standard machen (B-05).
     *
     * is_default steht bewusst nicht in #[Fillable]: Es darf je Familie nur
     * einen Standard geben, und das laesst sich nur sicherstellen, wenn das
     * Setzen durch diese eine Methode laeuft. Der Global Scope sorgt dafuer,
     * dass das Zuruecksetzen nur die eigene Familie trifft.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()->whereKeyNot($this->getKey())->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }
}
