<?php

namespace App\Core\Scopes;

use App\Core\Support\FamilyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtert jede Abfrage auf die Familie des laufenden Requests.
 *
 * Das ist die eine Stelle, die die vergessene WHERE-Klausel ersetzt -- der
 * klassische Multi-Tenancy-Bug, bei dem Daten fremder Familien sichtbar werden.
 *
 * Ohne Familienkontext liefert der Scope bewusst *keine* Zeilen statt alle
 * ("fail closed"). Wer absichtlich mandantenuebergreifend arbeitet -- etwa ein
 * Wartungsbefehl -- muss das sichtbar machen:
 *
 *   Model::withoutGlobalScope(FamilyScope::class)->get();
 *   app(FamilyContext::class)->runFor($familyId, fn () => ...);
 */
class FamilyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $familyId = app(FamilyContext::class)->id();

        $column = $model->getTable().'.family_id';

        if ($familyId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($column, $familyId);
    }
}
