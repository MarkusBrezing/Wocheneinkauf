<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Aktivitaetsprotokoll je Familie (G-06).
 *
 * Bewusst ohne Fremdschluessel und ohne BelongsToFamily: der Eintrag soll das
 * Loeschen von Familie oder Nutzer ueberleben und auch dann noch auswertbar
 * sein, wenn kein Familienkontext gesetzt ist.
 */
#[Fillable(['family_id', 'user_id', 'action', 'subject_type', 'subject_id', 'meta'])]
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $table = 'activity_log';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
