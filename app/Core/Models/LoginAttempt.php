<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Protokoll der Anmeldeversuche, Grundlage des Brute-Force-Schutzes (A-14).
 *
 * ip_address ist VARBINARY(16) und haelt das Ergebnis von inet_pton --
 * kompakt und gleich lang fuer IPv4 wie IPv6.
 */
#[Fillable(['email', 'ip_address', 'successful', 'attempted_at'])]
class LoginAttempt extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    /**
     * IP-Adresse in die Binaerform packen, in der die Spalte sie erwartet.
     */
    public static function packIp(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        return inet_pton($ip) ?: null;
    }
}
