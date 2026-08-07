<?php

namespace App\Http\Controllers;

use App\Core\Support\Handbuch;

/**
 * Das Anwenderhandbuch auf der Webseite.
 *
 * Ohne Anmeldung erreichbar: Die häufigste Frage kommt von jemandem, der
 * gerade *nicht* hineinkommt.
 */
class HandbuchController extends Controller
{
    public function index()
    {
        return view('handbuch.index', ['kapitel' => Handbuch::kapitel()]);
    }

    public function show(string $kapitel)
    {
        $gefunden = Handbuch::finden($kapitel) ?? abort(404);

        return view('handbuch.show', $gefunden + ['kapitel_liste' => Handbuch::kapitel()]);
    }

    /**
     * Alles am Stück -- zum Ausdrucken oder Durchsuchen mit Strg+F.
     */
    public function komplett()
    {
        return view('handbuch.komplett', ['abschnitte' => Handbuch::komplett()]);
    }
}
