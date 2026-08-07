<?php

namespace App\Modules\Pantry\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine Bestandsbuchung (E-02).
 *
 * Das Bewegungskonto beantwortet die Frage, die ein blosser Bestandswert
 * offenlaesst: warum hat sich die Menge geaendert? Ohne sie waere Schwund
 * nicht von einer Fehlbuchung zu unterscheiden.
 */
#[Fillable(['qty_delta', 'reason', 'ref_type', 'ref_id'])]
class PantryMovement extends Model
{
    use BelongsToFamily;

    public const UPDATED_AT = null;

    /**
     * @var array<string, string>
     */
    public const GRUENDE = [
        'purchase' => 'Einkauf',
        'consumption' => 'Verbrauch',
        'inventory' => 'Inventur',
        'correction' => 'Korrektur',
        'expired' => 'abgelaufen',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'qty_delta' => 'decimal:3',
            'created_at' => 'datetime',
        ];
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
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function grundLabel(): string
    {
        return self::GRUENDE[$this->reason] ?? $this->reason;
    }
}
