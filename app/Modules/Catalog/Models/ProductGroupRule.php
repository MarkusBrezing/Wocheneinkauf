<?php

namespace App\Modules\Catalog\Models;

use App\Core\Concerns\BelongsToFamily;
use Database\Factories\Catalog\ProductGroupRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stichwortregel der Auto-Kategorisierung (B-07).
 *
 * Kleinere priority gewinnt: spezielle Regeln ("kirschtomate") sollen vor
 * allgemeinen ("tomate") greifen.
 */
#[Fillable(['product_group_id', 'pattern', 'is_regex', 'priority'])]
class ProductGroupRule extends Model
{
    /** @use HasFactory<ProductGroupRuleFactory> */
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
            'is_regex' => 'boolean',
            'priority' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProductGroup, $this>
     */
    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class);
    }
}
