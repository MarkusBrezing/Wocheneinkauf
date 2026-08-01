<?php

namespace App\Core\Support;

use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Haelt fest, fuer welche Familie der laufende Request arbeitet.
 *
 * Im Normalfall ergibt sich der Mandant aus dem angemeldeten Nutzer. Fuer
 * Konsolenbefehle, Queue-Jobs und Tests laesst er sich explizit setzen -- dort
 * gibt es keinen angemeldeten Nutzer, und ohne Kontext liefert der Global Scope
 * bewusst gar nichts (siehe App\Core\Scopes\FamilyScope).
 */
class FamilyContext
{
    protected ?int $familyId = null;

    protected bool $explicit = false;

    /**
     * Die aktuell gueltige Familien-ID, oder null wenn keine bestimmbar ist.
     */
    public function id(): ?int
    {
        if ($this->explicit) {
            return $this->familyId;
        }

        return Auth::user()?->current_family_id;
    }

    /**
     * Kontext fest vorgeben und damit den angemeldeten Nutzer uebersteuern.
     */
    public function set(?int $familyId): void
    {
        $this->familyId = $familyId;
        $this->explicit = true;
    }

    /**
     * Zurueck zum Mandanten des angemeldeten Nutzers.
     */
    public function forget(): void
    {
        $this->familyId = null;
        $this->explicit = false;
    }

    /**
     * Callback voruebergehend im Kontext einer anderen Familie ausfuehren.
     *
     * @template TReturn
     *
     * @param  Closure():TReturn  $callback
     * @return TReturn
     */
    public function runFor(?int $familyId, Closure $callback): mixed
    {
        $previousId = $this->familyId;
        $previousExplicit = $this->explicit;

        $this->set($familyId);

        try {
            return $callback();
        } finally {
            $this->familyId = $previousId;
            $this->explicit = $previousExplicit;
        }
    }
}
