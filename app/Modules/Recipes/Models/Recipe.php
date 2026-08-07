<?php

namespace App\Modules\Recipes\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Concerns\HasPublicId;
use App\Core\Models\User;
use Database\Factories\Recipes\RecipeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Ein Rezept (D-01 … D-08).
 */
#[Fillable([
    'name', 'servings', 'prep_minutes', 'cook_minutes', 'effort',
    'instructions', 'notes', 'nutrition', 'plant_points', 'source_url',
])]
class Recipe extends Model
{
    /** @use HasFactory<RecipeFactory> */
    use BelongsToFamily, HasFactory, HasPublicId, SoftDeletes;

    protected static function booted(): void
    {
        // Der Slug folgt dem Namen, solange ihn niemand von Hand gesetzt hat.
        // Er ist je Familie eindeutig -- deshalb bei Kollision ein Zusatz,
        // statt den UNIQUE-Fehler bis in den Controller durchschlagen zu
        // lassen.
        static::saving(function (Recipe $recipe) {
            if ($recipe->isDirty('name') || $recipe->slug === null) {
                $recipe->slug = $recipe->freierSlug(Str::slug($recipe->name) ?: 'rezept');
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nutrition' => 'array',
            'is_favorite' => 'boolean',
            'servings' => 'integer',
            'prep_minutes' => 'integer',
            'cook_minutes' => 'integer',
            'plant_points' => 'integer',
        ];
    }

    /**
     * @return HasMany<RecipeIngredient, $this>
     */
    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'recipe_tag')->withPivot('family_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Freitextsuche ueber Name, Anmerkungen und Zutaten (D-01).
     *
     * Die Zutaten gehoeren dazu, weil man ein Rezept haeufiger ueber das sucht,
     * was weg muss ("noch Lauch im Kühlschrank"), als ueber seinen Namen.
     *
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeSuche(Builder $query, string $begriff): Builder
    {
        $begriff = trim($begriff);

        if ($begriff === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($begriff) {
            $q->where('name', 'like', '%'.$begriff.'%')
                ->orWhere('notes', 'like', '%'.$begriff.'%')
                ->orWhereHas('ingredients', fn (Builder $z) => $z->where('raw_name', 'like', '%'.$begriff.'%'));
        });
    }

    /**
     * @param  Builder<Recipe>  $query
     * @return Builder<Recipe>
     */
    public function scopeFavoriten(Builder $query): Builder
    {
        return $query->where('is_favorite', true);
    }

    /**
     * Die Gesamtzeit in Minuten, oder null wenn nichts erfasst ist.
     */
    public function gesamtzeit(): ?int
    {
        $summe = (int) $this->prep_minutes + (int) $this->cook_minutes;

        return $summe > 0 ? $summe : null;
    }

    public function aufwandLabel(): string
    {
        return match ($this->effort) {
            'quick' => 'schnell',
            'elaborate' => 'aufwendig',
            default => 'normal',
        };
    }

    /**
     * Ein je Familie freier Slug: "chili", dann "chili-2", "chili-3" …
     */
    protected function freierSlug(string $basis): string
    {
        $slug = $basis;
        $zaehler = 1;

        while (static::where('slug', $slug)->whereKeyNot($this->getKey())->withTrashed()->exists()) {
            $slug = $basis.'-'.(++$zaehler);
        }

        return $slug;
    }
}
