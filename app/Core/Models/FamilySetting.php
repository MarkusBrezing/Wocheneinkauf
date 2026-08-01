<?php

namespace App\Core\Models;

use App\Core\Concerns\BelongsToFamily;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Freie Einstellung je Familie (Key/Value), damit neue Module keine
 * Schemaaenderung brauchen.
 *
 * Zusammengesetzter Primaerschluessel (family_id, setting_key) -- Eloquent
 * kennt keine mehrspaltigen Schluessel, deshalb kein Auto-Increment und
 * Zugriff ueber updateOrCreate statt find().
 */
#[Fillable(['setting_key', 'value'])]
class FamilySetting extends Model
{
    use BelongsToFamily;

    const CREATED_AT = null;

    public $incrementing = false;

    protected $table = 'family_settings';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
