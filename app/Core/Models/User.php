<?php

namespace App\Core\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'locale'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPublicId, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Alle Familien, denen der Nutzer angehoert. Die UI beschraenkt das
     * vorerst auf eine, das Schema laesst mehrere zu (A-13).
     *
     * @return BelongsToMany<Family, $this>
     */
    public function families(): BelongsToMany
    {
        return $this->belongsToMany(Family::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function currentFamily(): BelongsTo
    {
        return $this->belongsTo(Family::class, 'current_family_id');
    }

    /**
     * Die Rolle des Nutzers in der Familie, oder null wenn er nicht Mitglied ist.
     */
    public function roleIn(Family|int|null $family): ?string
    {
        $familyId = $family instanceof Family ? $family->getKey() : $family;

        if ($familyId === null) {
            return null;
        }

        return $this->families()
            ->where('families.id', $familyId)
            ->first()?->pivot->role;
    }

    /**
     * Darf der Nutzer die Familie verwalten (einladen, Rollen aendern)?
     */
    public function administers(Family|int|null $family): bool
    {
        return in_array($this->roleIn($family), ['owner', 'admin'], true);
    }

    public function ownsFamily(Family|int|null $family): bool
    {
        return $this->roleIn($family) === 'owner';
    }
}
