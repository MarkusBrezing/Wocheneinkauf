<?php

namespace App\Modules\Pantry\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Concerns\HasPublicId;
use App\Core\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein Inventurlauf (E-03 … E-08).
 *
 * Je Familie darf hoechstens einer offen sein -- das erzwingt die Datenbank
 * ueber (family_id, offen_marker). Zwei gleichzeitige Zaehlungen wuerden sich
 * widersprechen, und hinterher waere nicht mehr zu klaeren, welche gilt.
 */
#[Fillable(['title', 'scope', 'scope_value', 'note'])]
class Inventory extends Model
{
    use BelongsToFamily, HasPublicId;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InventoryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InventoryLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    /**
     * @param  Builder<Inventory>  $query
     * @return Builder<Inventory>
     */
    public function scopeOffen(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    public function istOffen(): bool
    {
        return $this->status === 'open';
    }

    public function anzeigename(): string
    {
        return $this->title ?: 'Inventur vom '.$this->started_at?->format('d.m.Y');
    }

    public function umfangLabel(): string
    {
        return match ($this->scope) {
            'location' => 'Ort: '.$this->scope_value,
            'selection' => 'Auswahl',
            default => 'gesamter Vorrat',
        };
    }
}
