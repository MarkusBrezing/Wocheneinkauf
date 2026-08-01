<?php

namespace App\Core\Services;

use App\Core\Models\ActivityLog;
use App\Core\Models\Family;
use App\Core\Models\Invitation;
use App\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Einladungen erstellen, einloesen und widerrufen (A-06, A-07, A-09).
 *
 * Der Klartext-Token verlaesst diese Klasse genau einmal -- beim Erstellen, um
 * ihn in den Link zu schreiben. Gespeichert wird nur sein SHA-256-Hash; wer
 * spaeter die Datenbank liest, kann daraus keinen gueltigen Link bauen.
 */
class InvitationService
{
    public const DEFAULT_VALID_DAYS = 14;

    public function __construct(protected FamilyProvisioner $provisioner) {}

    /**
     * @return array{invitation: Invitation, token: string}
     */
    public function create(
        Family $family,
        User $inviter,
        ?string $email = null,
        string $role = 'member',
        int $maxUses = 1,
        int $validDays = self::DEFAULT_VALID_DAYS,
    ): array {
        $token = Str::random(48);

        $invitation = new Invitation([
            'email' => $email ? mb_strtolower($email) : null,
            'role' => $role,
            'max_uses' => $maxUses,
            'expires_at' => now()->addDays($validDays),
        ]);

        $invitation->family_id = $family->getKey();
        $invitation->invited_by = $inviter->getKey();
        $invitation->token_hash = Invitation::hashToken($token);
        $invitation->save();

        ActivityLog::create([
            'family_id' => $family->getKey(),
            'user_id' => $inviter->getKey(),
            'action' => 'invitation.created',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->getKey(),
            'meta' => ['role' => $role, 'email' => $invitation->email],
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }

    /**
     * Einladung zum Klartext-Token suchen, sofern sie noch einloesbar ist.
     *
     * Bewusst ohne Familienfilter: wer hier ankommt, gehoert noch zu keiner
     * Familie. Der Token selbst ist der Nachweis.
     */
    public function findRedeemable(string $token): ?Invitation
    {
        if ($token === '') {
            return null;
        }

        return Invitation::query()
            ->where('token_hash', Invitation::hashToken($token))
            ->redeemable()
            ->with('family')
            ->first();
    }

    /**
     * Einladung einloesen und den Nutzer in die Familie aufnehmen (A-07).
     */
    public function redeem(Invitation $invitation, User $user): Family
    {
        return DB::transaction(function () use ($invitation, $user) {
            // Erneut sperren und pruefen, damit zwei gleichzeitige Aufrufe das
            // Kontingent nicht ueberziehen.
            $frisch = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->first();

            if (! $frisch || ! $frisch->isRedeemable()) {
                throw new InvitationNotRedeemable;
            }

            $family = $frisch->family()->firstOrFail();

            $this->provisioner->join($user, $family, $frisch->role);

            $frisch->increment('used_count');

            ActivityLog::create([
                'family_id' => $family->getKey(),
                'user_id' => $user->getKey(),
                'action' => 'invitation.redeemed',
                'subject_type' => 'invitation',
                'subject_id' => $frisch->getKey(),
            ]);

            return $family;
        });
    }

    /**
     * Einladung widerrufen (A-09).
     */
    public function revoke(Invitation $invitation, User $actor): void
    {
        $invitation->forceFill(['revoked_at' => now()])->save();

        ActivityLog::create([
            'family_id' => $invitation->family_id,
            'user_id' => $actor->getKey(),
            'action' => 'invitation.revoked',
            'subject_type' => 'invitation',
            'subject_id' => $invitation->getKey(),
        ]);
    }
}
