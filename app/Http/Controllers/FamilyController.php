<?php

namespace App\Http\Controllers;

use App\Core\Models\ActivityLog;
use App\Core\Models\Family;
use App\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Familieneinstellungen und Mitgliederverwaltung (A-08, A-10, A-13).
 */
class FamilyController extends Controller
{
    /**
     * Uebersicht mit Einstellungen, Mitgliedern und offenen Einladungen.
     */
    public function edit(Request $request)
    {
        $family = $this->currentFamily($request);

        return view('family.edit', [
            'family' => $family,
            'mitglieder' => $family->users()->orderBy('name')->get(),
            'einladungen' => $family->invitations()->redeemable()->latest('created_at')->get(),
            'darfVerwalten' => $request->user()->administers($family),
            'istOwner' => $request->user()->ownsFamily($family),
        ]);
    }

    /**
     * Familie umbenennen, Zeitzone und Wochenstart einstellen (A-10).
     */
    public function update(Request $request)
    {
        $family = $this->currentFamily($request);
        $this->erzwingeVerwaltungsrecht($request, $family);

        $daten = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'max:64', Rule::in(timezone_identifiers_list())],
            'week_starts_on' => ['required', 'integer', 'between:1,7'],
        ]);

        $family->update($daten);

        ActivityLog::create([
            'family_id' => $family->getKey(),
            'user_id' => $request->user()->getKey(),
            'action' => 'family.updated',
            'subject_type' => 'family',
            'subject_id' => $family->getKey(),
        ]);

        return back()->with('status', 'Die Familieneinstellungen wurden gespeichert.');
    }

    /**
     * Rolle eines Mitglieds aendern (A-08). Nur der Owner darf das.
     */
    public function updateMember(Request $request, string $mitglied)
    {
        $family = $this->currentFamily($request);
        $this->erzwingeOwner($request, $family);

        $daten = $request->validate([
            'role' => ['required', Rule::in(['admin', 'member', 'child'])],
        ]);

        $ziel = $this->mitgliedFinden($family, $mitglied);
        $this->schuetzeLetztenOwner($family, $ziel, 'Die Familie braucht mindestens einen Owner.');

        $family->users()->updateExistingPivot($ziel->getKey(), ['role' => $daten['role']]);

        ActivityLog::create([
            'family_id' => $family->getKey(),
            'user_id' => $request->user()->getKey(),
            'action' => 'member.role_changed',
            'subject_type' => 'user',
            'subject_id' => $ziel->getKey(),
            'meta' => ['role' => $daten['role']],
        ]);

        return back()->with('status', $ziel->name.' hat jetzt die Rolle '.$daten['role'].'.');
    }

    /**
     * Mitglied aus der Familie entfernen (A-08).
     */
    public function destroyMember(Request $request, string $mitglied)
    {
        $family = $this->currentFamily($request);
        $this->erzwingeOwner($request, $family);

        $ziel = $this->mitgliedFinden($family, $mitglied);
        $this->schuetzeLetztenOwner($family, $ziel, 'Der letzte Owner kann die Familie nicht verlassen.');

        DB::transaction(function () use ($family, $ziel, $request) {
            $family->users()->detach($ziel->getKey());

            // Sonst zeigt der Nutzer weiter auf eine Familie, die er nicht mehr
            // sehen darf -- der Global Scope wuerde ihm fremde Daten filtern,
            // aber der Kontext waere falsch.
            if ($ziel->current_family_id === $family->getKey()) {
                $ziel->forceFill([
                    'current_family_id' => $ziel->families()->value('families.id'),
                ])->save();
            }

            ActivityLog::create([
                'family_id' => $family->getKey(),
                'user_id' => $request->user()->getKey(),
                'action' => 'member.removed',
                'subject_type' => 'user',
                'subject_id' => $ziel->getKey(),
            ]);
        });

        return back()->with('status', $ziel->name.' gehört nicht mehr zur Familie.');
    }

    /**
     * Zwischen Familien wechseln (A-13).
     */
    public function switch(Request $request)
    {
        $daten = $request->validate([
            'family' => ['required', 'string', 'size:26'],
        ]);

        $ziel = $request->user()->families()
            ->where('families.public_id', $daten['family'])
            ->first();

        if (! $ziel) {
            throw ValidationException::withMessages([
                'family' => 'Du gehörst dieser Familie nicht an.',
            ]);
        }

        $request->user()->forceFill(['current_family_id' => $ziel->getKey()])->save();

        return back()->with('status', 'Du arbeitest jetzt in '.$ziel->name.'.');
    }

    protected function currentFamily(Request $request): Family
    {
        return $request->user()->currentFamily
            ?? abort(409, 'Für dieses Konto ist keine Familie gesetzt.');
    }

    protected function mitgliedFinden(Family $family, string $publicId): User
    {
        return $family->users()->where('users.public_id', $publicId)->firstOrFail();
    }

    protected function erzwingeVerwaltungsrecht(Request $request, Family $family): void
    {
        abort_unless($request->user()->administers($family), 403);
    }

    protected function erzwingeOwner(Request $request, Family $family): void
    {
        abort_unless($request->user()->ownsFamily($family), 403);
    }

    /**
     * Verhindert, dass eine Familie ohne Owner zurueckbleibt -- sie waere sonst
     * unverwaltbar, weil nur der Owner Rollen vergeben darf.
     */
    protected function schuetzeLetztenOwner(Family $family, User $ziel, string $meldung): void
    {
        if ($ziel->roleIn($family) !== 'owner') {
            return;
        }

        $weitereOwner = $family->usersWithRole(['owner'])
            ->where('users.id', '!=', $ziel->getKey())
            ->exists();

        if (! $weitereOwner) {
            throw ValidationException::withMessages(['role' => $meldung]);
        }
    }
}
