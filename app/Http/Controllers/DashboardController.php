<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Startseite nach der Anmeldung. Bis Schritt 4 die Einkaufsliste bringt,
     * zeigt sie nur den Mandantenkontext -- das reicht, um zu sehen, ob
     * Anmeldung und Familienzuordnung greifen.
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('dashboard', [
            'familien' => $user->families()->get(),
            'aktuelleFamilie' => $user->currentFamily,
        ]);
    }
}
