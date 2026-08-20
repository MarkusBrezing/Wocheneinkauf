<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Schutzkopfzeilen für jede Antwort.
 *
 * Sie kosten nichts und nehmen einem Angreifer, der irgendwo doch eine Lücke
 * findet, die bequemen Wege: kein Einbetten in fremde Seiten, kein Nachladen
 * von Skripten anderswoher, kein Abfluss von Daten an einen fremden Host.
 */
class SicherheitsKopfzeilen
{
    public function handle(Request $request, Closure $next): Response
    {
        $antwort = $next($request);

        /*
         * Die Anwendung bindet nichts von fremden Servern ein -- keine
         * Schriften, keine Skripte, kein Content Delivery Network. Genau das
         * lässt sich hier festschreiben, und dann bleibt es auch so.
         *
         * 'unsafe-inline' ist nötig, weil Stile und die wenigen Skripte in den
         * Layouts stehen. Das schwächt den Schutz gegen eingeschleustes
         * Skript, nicht aber gegen das, was danach käme: Nachladen und
         * Abfließen scheitern weiterhin an default-src 'self'.
         */
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
        ]);

        $antwort->headers->set('Content-Security-Policy', $csp);

        // Kein Einbetten in fremde Seiten: Die Anwendung hat Knöpfe, die
        // Daten löschen -- Clickjacking wäre hier teuer.
        $antwort->headers->set('X-Frame-Options', 'DENY');

        // Kein Raten des Inhaltstyps.
        $antwort->headers->set('X-Content-Type-Options', 'nosniff');

        // Beim Weggehen keine vollständige Adresse mitgeben: Listen- und
        // Rezeptadressen enthalten ULIDs, die niemanden angehen.
        $antwort->headers->set('Referrer-Policy', 'same-origin');

        /*
         * HSTS: Der Browser merkt sich, dass diese Adresse nur über HTTPS zu
         * erreichen ist, und ruft sie beim nächsten Mal gar nicht erst
         * unverschlüsselt auf. Das schließt die Lücke, die eine
         * Server-Umleitung offen lässt -- die erste Anfrage geht sonst
         * jedes Mal im Klartext hinaus, samt Sitzungscookie-Versuch.
         *
         * Nur über HTTPS gesetzt: Über eine unverschlüsselte Verbindung
         * ignorieren Browser die Kopfzeile ohnehin, und in der lokalen
         * Entwicklung würde sie den Zugriff über http://localhost auf Monate
         * hinaus blockieren.
         *
         * Bewusst OHNE includeSubDomains: Das würde HTTPS auch für jede
         * Subdomain erzwingen. einkauf.brezing.info hat kein Zertifikat --
         * die Adresse wäre damit für die Dauer von max-age unerreichbar,
         * auch für die 403-Sperre, die dort absichtlich steht.
         */
        if ($request->secure()) {
            $antwort->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $antwort;
    }
}
