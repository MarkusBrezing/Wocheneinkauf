<?php

namespace App\Modules\ShoppingList\Models;

use App\Core\Concerns\BelongsToFamily;
use App\Core\Concerns\HasPublicId;
use Database\Factories\ShoppingList\ListTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Eine Vorlage fuer neue Einkaufslisten.
 *
 * Der Unterschied zum Grundvorrat (B-08): Der Grundvorrat ist *die eine*
 * Liste dessen, was ohnehin im Haus sein soll. Vorlagen sind benannt, es darf
 * mehrere geben, und sie beschreiben einen Anlass -- "Grillabend",
 * "Fruehstueck fuer Gaeste", "Urlaubsvorrat".
 */
#[Fillable(['name', 'description', 'sort'])]
class ListTemplate extends Model
{
    /** @use HasFactory<ListTemplateFactory> */
    use BelongsToFamily, HasFactory, HasPublicId;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return HasMany<ListTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ListTemplateItem::class, 'template_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * Diese Vorlage beim Anlegen einer neuen Liste automatisch uebernehmen.
     *
     * Wie beim Standardmarkt (B-05) steht is_default nicht in #[Fillable]: Es
     * darf je Familie nur eine geben, und das laesst sich nur sicherstellen,
     * wenn das Setzen durch diese eine Methode laeuft.
     */
    public function makeDefault(): void
    {
        DB::transaction(function () {
            static::query()->whereKeyNot($this->getKey())->update(['is_default' => false]);

            $this->forceFill(['is_default' => true])->save();
        });
    }

    public function clearDefault(): void
    {
        $this->forceFill(['is_default' => false])->save();
    }

    /**
     * Die Standardvorlage der Familie, falls eine gesetzt ist.
     */
    public static function standard(): ?self
    {
        return static::where('is_default', true)->with('items')->first();
    }
}
