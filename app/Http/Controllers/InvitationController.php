<?php

namespace App\Http\Controllers;

use App\Core\Models\Family;
use App\Core\Notifications\FamilyInvitation;
use App\Core\Services\InvitationNotRedeemable;
use App\Core\Services\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

/**
 * Einladungen versenden, annehmen und widerrufen (A-06, A-07, A-09).
 */
class InvitationController extends Controller
{
    public function __construct(protected InvitationService $invitations) {}

    /**
     * Einladung erstellen (A-06).
     *
     * Ohne E-Mail-Adresse entsteht ein offener Link, den der Einladende selbst
     * weitergibt -- praktisch fuer Familienmitglieder, deren Adresse man nicht
     * zur Hand hat.
     */
    public function store(Request $request)
    {
        $family = $this->currentFamily($request);
        abort_unless($request->user()->administers($family), 403);

        $daten = $request->validate([
            'email' => ['nullable', 'email', 'max:190'],
            'role' => ['required', Rule::in(['admin', 'member', 'child'])],
        ]);

        ['invitation' => $einladung, 'token' => $token] = $this->invitations->create(
            family: $family,
            inviter: $request->user(),
            email: $daten['email'] ?? null,
            role: $daten['role'],
        );

        $link = route('invitation.show', ['token' => $token]);

        if ($einladung->email) {
            Notification::route('mail', $einladung->email)
                ->notify(new FamilyInvitation($family, $request->user()->name, $link, $einladung->expires_at));

            return back()->with('status', 'Einladung an '.$einladung->email.' verschickt.');
        }

        // Der Klartext-Token existiert nur in dieser Antwort; die Datenbank
        // kennt bloss seinen Hash. Deshalb einmalig im Flash anzeigen.
        return back()->with('status', 'Einladungslink erstellt: '.$link);
    }

    /**
     * Einladung oeffnen (A-07).
     *
     * Angemeldete Nutzer koennen direkt beitreten, alle anderen sehen das
     * Registrierungsformular mit vorbelegter Adresse.
     */
    public function show(Request $request, string $token)
    {
        $einladung = $this->invitations->findRedeemable($token);

        if (! $einladung) {
            return view('invitation.invalid');
        }

        if ($request->user()) {
            return view('invitation.accept', [
                'einladung' => $einladung,
                'token' => $token,
            ]);
        }

        return view('auth.register', [
            'einladung' => $einladung,
            'token' => $token,
        ]);
    }

    /**
     * Einladung als angemeldeter Nutzer annehmen (A-07, Variante
     * "bestehendes Konto").
     */
    public function accept(Request $request, string $token)
    {
        $einladung = $this->invitations->findRedeemable($token);

        if (! $einladung) {
            return view('invitation.invalid');
        }

        try {
            $family = $this->invitations->redeem($einladung, $request->user());
        } catch (InvitationNotRedeemable $e) {
            return view('invitation.invalid');
        }

        return redirect()->route('dashboard')
            ->with('status', 'Du gehörst jetzt zu '.$family->name.'.');
    }

    /**
     * Einladung widerrufen (A-09).
     */
    public function destroy(Request $request, int $einladung)
    {
        $family = $this->currentFamily($request);
        abort_unless($request->user()->administers($family), 403);

        // Ueber die Beziehung suchen, damit sich keine fremde Einladung
        // widerrufen laesst.
        $ziel = $family->invitations()->whereKey($einladung)->firstOrFail();

        $this->invitations->revoke($ziel, $request->user());

        return back()->with('status', 'Die Einladung wurde widerrufen.');
    }

    protected function currentFamily(Request $request): Family
    {
        return $request->user()->currentFamily
            ?? abort(409, 'Für dieses Konto ist keine Familie gesetzt.');
    }
}
