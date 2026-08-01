<?php

namespace App\Core\Services;

use App\Core\Models\LoginAttempt;
use Illuminate\Support\Facades\Cache;

/**
 * Protokolliert Anmeldeversuche und beantwortet, ob ein Konto gesperrt ist
 * (A-14).
 *
 * Ergaenzt Fortifys Rate Limiter, der nur im Cache zaehlt und deshalb einen
 * Neustart oder einen geleerten Cache nicht ueberlebt. Die Tabelle haelt
 * zusaetzlich fest, *dass* jemand es versucht hat -- Grundlage fuer spaetere
 * Benachrichtigungen bei verdaechtigen Anmeldungen.
 */
class LoginAttemptRecorder
{
    /** Ab so vielen Fehlversuchen im Zeitfenster wird gesperrt. */
    public const LOCKOUT_THRESHOLD = 10;

    /** Beobachtungsfenster und zugleich Sperrdauer in Minuten. */
    public const LOCKOUT_MINUTES = 15;

    /** Aufbewahrungsfrist des Protokolls in Tagen (Review-Befund R-07). */
    public const RETENTION_DAYS = 90;

    public function record(string $email, ?string $ip, bool $successful): void
    {
        LoginAttempt::create([
            'email' => mb_strtolower($email),
            'ip_address' => LoginAttempt::packIp($ip),
            'successful' => $successful,
            'attempted_at' => now(),
        ]);

        $this->pruneOccasionally();
    }

    /**
     * Zu viele Fehlversuche im Zeitfenster -- und seither kein Erfolg?
     */
    public function isLockedOut(string $email, ?string $ip): bool
    {
        if ($email === '') {
            return false;
        }

        $since = now()->subMinutes(self::LOCKOUT_MINUTES);

        $failures = LoginAttempt::query()
            ->where('email', mb_strtolower($email))
            ->where('attempted_at', '>=', $since)
            ->where('successful', false)
            ->count();

        if ($failures < self::LOCKOUT_THRESHOLD) {
            return false;
        }

        // Eine erfolgreiche Anmeldung im selben Fenster hebt die Sperre auf --
        // sonst sperrt ein Fremder ein Konto aus, indem er es beschiesst.
        $succeededSince = LoginAttempt::query()
            ->where('email', mb_strtolower($email))
            ->where('attempted_at', '>=', $since)
            ->where('successful', true)
            ->exists();

        return ! $succeededSince;
    }

    /**
     * Alte Eintraege entfernen -- lazy beim Schreiben, weil auf dem Zieltarif
     * kein Cronjob zur Verfuegung steht.
     */
    protected function pruneOccasionally(): void
    {
        Cache::remember('login_attempts.pruned_at', now()->addHour(), function () {
            LoginAttempt::query()
                ->where('attempted_at', '<', now()->subDays(self::RETENTION_DAYS))
                ->delete();

            return now()->toIso8601String();
        });
    }
}
