<?php

namespace App\Modules\ShoppingList\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Concerns\HasPublicId;
use App\Core\Models\User;
use App\Modules\Markets\Models\Market;
use Database\Factories\ShoppingList\ShoppingListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eine Einkaufsliste (C-01 … C-17).
 *
 * Je Familie gibt es genau eine aktive Liste; alles Aeltere ist Archiv. Die
 * Datenbank erzwingt das ueber einen UNIQUE auf (family_id, aktiv_marker) --
 * siehe Migration.
 */
#[Fillable(['name', 'market_id'])]
class ShoppingList extends Model
{
    /** @use HasFactory<ShoppingListFactory> */
    use BelongsToFamily, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * @return HasMany<ShoppingListItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShoppingListItem::class, 'list_id');
    }

    /**
     * @return BelongsTo<Market, $this>
     */
    public function market(): BelongsTo
    {
        return $this->belongsTo(Market::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<ShoppingList>  $query
     * @return Builder<ShoppingList>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<ShoppingList>  $query
     * @return Builder<ShoppingList>
     */
    public function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', 'archived');
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    /**
     * Version hochzaehlen -- der Herzschlag des Delta-Syncs (C-07).
     *
     * Bewusst als increment() in der Datenbank statt als $this->version + 1:
     * zwei gleichzeitige Requests wuerden sonst dieselbe Version schreiben und
     * genau den Konflikt verstecken, den die Spalte sichtbar machen soll.
     * updated_at zieht increment() selbst mit.
     */
    public function bumpVersion(): void
    {
        $this->increment('version');
    }

    /**
     * Dasselbe ohne geladenes Model -- fuer die Hooks der Listeneintraege,
     * die sonst zu jeder Aenderung erst die Liste nachladen muessten.
     */
    public static function bumpVersionFor(int $listId): void
    {
        static::whereKey($listId)->increment('version');
    }

    /**
     * Anzeigename: was der Nutzer vergeben hat, sonst das Anlagedatum.
     */
    public function anzeigename(): string
    {
        return $this->name ?: 'Einkauf vom '.$this->created_at?->format('d.m.Y');
    }
}
