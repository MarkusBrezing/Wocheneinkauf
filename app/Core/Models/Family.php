<?php

namespace App\Core\Models;

use App\Core\Concerns\HasPublicId;
use Database\Factories\FamilyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Der Mandant. Jede fachliche Tabelle haengt ueber family_id hieran.
 */
#[Fillable(['name', 'locale', 'timezone', 'week_starts_on'])]
class Family extends Model
{
    /** @use HasFactory<FamilyFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'week_starts_on' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot(['role', 'joined_at']);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<FamilySetting, $this>
     */
    public function settings(): HasMany
    {
        return $this->hasMany(FamilySetting::class);
    }

    /**
     * Die Nutzer mit einer der genannten Rollen.
     *
     * @param  array<int, string>  $roles
     * @return BelongsToMany<User, $this>
     */
    public function usersWithRole(array $roles): BelongsToMany
    {
        return $this->users()->wherePivotIn('role', $roles);
    }
}
