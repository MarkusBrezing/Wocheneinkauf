<?php

namespace App\Core\Services;

use App\Core\Models\ActivityLog;
use App\Core\Models\Family;
use App\Core\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Legt Familien an und nimmt Nutzer in sie auf.
 *
 * Beides passiert an mehreren Stellen -- bei der Registrierung (A-01), beim
 * Einloesen einer Einladung (A-07) und im Seeder -- und muss ueberall dieselbe
 * Nachbereitung machen: Mitgliedschaft eintragen und den aktuellen Mandanten
 * des Nutzers setzen.
 */
class FamilyProvisioner
{
    /**
     * Neue Familie anlegen und den Nutzer als Owner eintragen (A-01).
     */
    public function createFor(User $user, ?string $name = null): Family
    {
        return DB::transaction(function () use ($user, $name) {
            $family = Family::create([
                'name' => $name ?: 'Familie '.$user->name,
            ]);

            $this->join($user, $family, 'owner');

            return $family;
        });
    }

    /**
     * Nutzer in eine bestehende Familie aufnehmen (A-07).
     *
     * Ist er bereits Mitglied, bleibt seine Rolle unveraendert -- eine zweite
     * Einladung darf einen Owner nicht zum Mitglied herabstufen.
     */
    public function join(User $user, Family $family, string $role = 'member'): void
    {
        DB::transaction(function () use ($user, $family, $role) {
            $alreadyMember = $family->users()->whereKey($user->getKey())->exists();

            if (! $alreadyMember) {
                $family->users()->attach($user, [
                    'role' => $role,
                    'joined_at' => now(),
                ]);
            }

            if ($user->current_family_id === null) {
                $user->forceFill(['current_family_id' => $family->getKey()])->save();
            }

            if (! $alreadyMember) {
                ActivityLog::create([
                    'family_id' => $family->getKey(),
                    'user_id' => $user->getKey(),
                    'action' => 'family.joined',
                    'subject_type' => 'family',
                    'subject_id' => $family->getKey(),
                    'meta' => ['role' => $role],
                ]);
            }
        });
    }
}
