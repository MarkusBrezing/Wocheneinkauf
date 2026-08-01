<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Einladung in eine Familie (A-06, A-07, A-09).
 *
 * Bewusst *ohne* BelongsToFamily: Wer eine Einladung einloest, gehoert noch zu
 * keiner Familie -- der Global Scope wuerde den Datensatz verstecken. Der
 * Zugriff laeuft stattdessen entweder ueber $family->invitations() oder ueber
 * den geheimen Token.
 */
#[Fillable(['email', 'role', 'max_uses', 'expires_at'])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'max_uses' => 'integer',
            'used_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Gespeichert wird nur der SHA-256-Hash; der Klartext steht allein im Link.
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Noch einloesbar: nicht widerrufen, nicht abgelaufen, Kontingent uebrig.
     */
    public function isRedeemable(): bool
    {
        return $this->revoked_at === null
            && $this->expires_at->isFuture()
            && $this->used_count < $this->max_uses;
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeRedeemable(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->whereColumn('used_count', '<', 'max_uses');
    }
}
