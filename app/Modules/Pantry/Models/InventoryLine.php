<?php

namespace App\Modules\Pantry\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Zeile des Inventurlaufs.
 *
 * counted_qty bleibt NULL, solange nicht gezaehlt wurde -- daran erkennt jeder
 * Mitzaehler, was noch offen ist. Deshalb koennen mehrere Personen gleichzeitig
 * an verschiedenen Orten zaehlen (E-05), ohne sich abzusprechen.
 */
#[Fillable(['counted_qty', 'note'])]
class InventoryLine extends Model
{
    use BelongsToFamily;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expected_qty' => 'decimal:3',
            'counted_qty' => 'decimal:3',
            'target_qty' => 'decimal:3',
            'difference' => 'decimal:3',
            'counted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Inventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }

    /**
     * @return BelongsTo<PantryItem, $this>
     */
    public function pantryItem(): BelongsTo
    {
        return $this->belongsTo(PantryItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * @param  Builder<InventoryLine>  $query
     * @return Builder<InventoryLine>
     */
    public function scopeUngezaehlt(Builder $query): Builder
    {
        return $query->whereNull('counted_qty');
    }

    public function istGezaehlt(): bool
    {
        return $this->counted_qty !== null;
    }

    /**
     * Was nach der Zaehlung zum Soll fehlt (E-09).
     */
    public function fehlmenge(): ?float
    {
        if (! $this->istGezaehlt()) {
            return null;
        }

        $fehlt = round((float) $this->target_qty - (float) $this->counted_qty, 3);

        return $fehlt > 0.0 ? $fehlt : null;
    }
}
