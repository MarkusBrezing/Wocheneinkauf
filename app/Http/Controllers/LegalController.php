<?php

namespace App\Http\Controllers;

/**
 * Impressum, Datenschutzerklärung und Nutzungsbedingungen.
 *
 * Ausdrücklich ohne Anmeldung erreichbar -- ein Impressum hinter einem Login
 * ist keines. Die Angaben kommen aus der Konfiguration, die Texte aus den
 * Views.
 */
class LegalController extends Controller
{
    public function impressum()
    {
        return view('rechtliches.impressum', ['betreiber' => config('rechtliches.betreiber')]);
    }

    public function datenschutz()
    {
        return view('rechtliches.datenschutz', ['betreiber' => config('rechtliches.betreiber')]);
    }

    public function nutzungsbedingungen()
    {
        return view('rechtliches.nutzungsbedingungen', ['betreiber' => config('rechtliches.betreiber')]);
    }
}
