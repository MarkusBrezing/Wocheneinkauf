<?php

namespace App\Core\Concerns;

use App\Core\Models\Family;
use App\Core\Scopes\FamilyScope;
use App\Core\Support\FamilyContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Macht ein Model mandantenfaehig: Lesen wird auf die aktuelle Familie
 * gefiltert, Schreiben traegt die family_id automatisch nach.
 *
 * Nicht geeignet fuer Datensaetze, die *ausserhalb* eines Familienkontexts
 * gefunden werden muessen -- eine Einladung etwa wird per Token von jemandem
 * geoeffnet, der noch zu keiner Familie gehoert. Solche Models greifen
 * stattdessen ueber die Beziehung der Familie zu.
 */
trait BelongsToFamily
{
    public static function bootBelongsToFamily(): void
    {
        static::addGlobalScope(new FamilyScope);

        static::creating(function ($model) {
            $model->family_id ??= app(FamilyContext::class)->id();
        });
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
