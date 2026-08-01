<?php

namespace App\Http\Controllers;

use App\Core\Models\ActivityLog;
use App\Core\Models\Family;
use App\Core\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Eigenes Konto: Auskunft und Loeschung nach DSGVO (A-11, A-12).
 */
class AccountController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $family = $user->currentFamily;

        return view('account.edit', [
            'user' => $user,
            'family' => $family,
            'istOwner' => $family && $user->ownsFamily($family),
            'weitereMitglieder' => $family
                ? $family->users()->where('users.id', '!=', $user->getKey())->count()
                : 0,
        ]);
    }

    /**
     * Auskunft nach Art. 15 DSGVO: alle zur Person gespeicherten Daten als
     * JSON zum Herunterladen (A-11).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        $daten = [
            'erzeugt_am' => now()->toIso8601String(),
            'konto' => [
                'id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'sprache' => $user->locale,
                'email_bestaetigt_am' => $user->email_verified_at?->toIso8601String(),
                'letzte_anmeldung' => $user->last_login_at?->toIso8601String(),
                'registriert_am' => $user->created_at?->toIso8601String(),
            ],
            'familien' => $user->families()->get()->map(fn (Family $family) => [
                'id' => $family->public_id,
                'name' => $family->name,
                'rolle' => $family->pivot->role,
                'beigetreten_am' => $family->pivot->joined_at,
                'zeitzone' => $family->timezone,
                'wochenstart' => $family->week_starts_on,
            ])->all(),
            'aktivitaeten' => ActivityLog::query()
                ->where('user_id', $user->getKey())
                ->orderBy('created_at')
                ->get(['action', 'subject_type', 'subject_id', 'meta', 'created_at'])
                ->all(),
        ];

        return response()->json($daten, 200, [
            'Content-Disposition' => 'attachment; filename="wocheneinkauf-auskunft.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Konto loeschen (A-12, Art. 17).
     *
     * Die E-Mail-Adresse wird dabei anonymisiert. Das ist nicht nur
     * datenschutzrechtlich geboten, sondern loest auch den im Review
     * beschriebenen Konflikt R-04: users.email ist UNIQUE, ein soft-geloeschter
     * Datensatz wuerde die Adresse sonst dauerhaft blockieren und eine erneute
     * Registrierung unmoeglich machen.
     */
    public function destroy(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $this->erzwingeNachfolgeFuerOwnerRollen($user);

        DB::transaction(function () use ($user, $request) {
            $user->families()->detach();

            $user->forceFill([
                'email' => 'deleted-'.$user->getKey().'@invalid',
                'name' => 'Gelöschtes Konto',
                'current_family_id' => null,
                'remember_token' => null,
            ])->save();

            $user->delete();

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        });

        return redirect('/')->with('status', 'Dein Konto wurde gelöscht.');
    }

    /**
     * Familie samt aller Daten loeschen (A-12, Art. 17).
     *
     * Endgueltig, nicht als Soft Delete: Artikel 17 verlangt echtes Loeschen.
     * Die Fremdschluessel raeumen die abhaengigen Tabellen per ON DELETE
     * CASCADE mit ab.
     */
    public function destroyFamily(Request $request)
    {
        $user = $request->user();
        $family = $user->currentFamily;

        abort_unless($family && $user->ownsFamily($family), 403);

        $request->validate([
            'password' => ['required', 'current_password'],
            'bestaetigung' => ['required', 'in:'.$family->name],
        ], [
            'bestaetigung.in' => 'Bitte gib zur Bestätigung den Familiennamen genau so ein, wie er oben steht.',
        ]);

        $name = $family->name;

        DB::transaction(function () use ($family) {
            $mitglieder = $family->users()->pluck('users.id');

            $family->forceDelete();

            // Der Fremdschluessel setzt current_family_id auf NULL; die Nutzer
            // brauchen aber wieder einen gueltigen Kontext, sonst sehen sie
            // nach dem naechsten Login gar nichts.
            User::whereIn('id', $mitglieder)->whereNull('current_family_id')->get()
                ->each(function (User $mitglied) {
                    $mitglied->forceFill([
                        'current_family_id' => $mitglied->families()->value('families.id'),
                    ])->save();
                });
        });

        return redirect()->route('dashboard')
            ->with('status', 'Die Familie "'.$name.'" wurde mit allen Daten gelöscht.');
    }

    /**
     * Ein Owner darf nicht verschwinden und eine Familie mit anderen
     * Mitgliedern fuehrerlos zuruecklassen.
     */
    protected function erzwingeNachfolgeFuerOwnerRollen(User $user): void
    {
        foreach ($user->families()->get() as $family) {
            if ($family->pivot->role !== 'owner') {
                continue;
            }

            $andereOwner = $family->usersWithRole(['owner'])
                ->where('users.id', '!=', $user->getKey())
                ->exists();

            $andereMitglieder = $family->users()
                ->where('users.id', '!=', $user->getKey())
                ->exists();

            if (! $andereOwner && $andereMitglieder) {
                throw ValidationException::withMessages([
                    'password' => 'Du bist der letzte Owner von "'.$family->name.'". '
                        .'Übertrage die Rolle an ein anderes Mitglied oder lösche die Familie zuerst.',
                ]);
            }

            // Letztes Mitglied: die Familie waere sonst eine Dateileiche.
            if (! $andereMitglieder) {
                $family->forceDelete();
            }
        }
    }
}
