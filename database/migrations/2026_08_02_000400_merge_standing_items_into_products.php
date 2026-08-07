<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fixartikel und Grundvorrat waren zwei Namen fuer dieselbe Sache.
 *
 * `standing_items` fuehrte, was jede Woche auf die Liste soll;
 * `products.is_pantry_staple` kennzeichnete, was im Schrank steht. In der
 * Praxis ist das derselbe Bestand -- Milch, Butter, Mehl, Klopapier. Zwei
 * Pflegeorte fuer eine Sache bedeuten aber doppelte Arbeit und die stete
 * Frage, welcher der beiden nun gilt.
 *
 * Ab hier gibt es nur noch den Grundvorrat: ein Kennzeichen am Artikel, dazu
 * die Sollmenge, die vorher am Fixartikel hing. Der Artikelkatalog ist damit
 * der einzige Ort, an dem Artikel gepflegt werden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Die Sollmenge des Grundvorrats -- wie viel davon im Haus sein
            // soll. Vorher standing_items.amount; die Einheit kommt aus dem
            // vorhandenen default_unit.
            $table->decimal('pantry_target_amount', 10, 3)->nullable()->after('is_pantry_staple');
        });

        $this->uebernehmen();

        Schema::dropIfExists('standing_items');
    }

    public function down(): void
    {
        // Die Tabelle im Zustand von Schritt 3 wiederherstellen.
        Schema::create('standing_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('raw_name', 160);
            $table->decimal('amount', 10, 3)->nullable();
            $table->string('unit', 20)->nullable();
            $table->foreignId('product_group_id')->nullable()
                ->constrained('product_groups')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['family_id', 'is_active', 'sort']);
        });

        foreach (DB::table('products')->where('is_pantry_staple', true)->get() as $produkt) {
            DB::table('standing_items')->insert([
                'family_id' => $produkt->family_id,
                'product_id' => $produkt->id,
                'raw_name' => $produkt->name,
                'amount' => $produkt->pantry_target_amount,
                'unit' => $produkt->default_unit,
                'product_group_id' => $produkt->product_group_id,
                'is_active' => true,
                'sort' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('pantry_target_amount');
        });
    }

    /**
     * Jeden Fixartikel in den Katalog ueberfuehren.
     *
     * Verknuepfte Fixartikel aktualisieren ihren Artikel, unverknuepfte legen
     * einen an -- so geht keine Vorlage verloren, auch wenn sie nie mit dem
     * Katalog verbunden war. Deaktivierte Fixartikel werden uebersprungen: Sie
     * waren abgeschaltet, und ein abgeschalteter Grundvorrat ist schlicht
     * keiner.
     */
    protected function uebernehmen(): void
    {
        if (! Schema::hasTable('standing_items')) {
            return;
        }

        foreach (DB::table('standing_items')->where('is_active', true)->orderBy('sort')->get() as $fix) {
            $produktId = $fix->product_id ?? $this->produktZuName($fix);

            if ($produktId === null) {
                $produktId = $this->artikelAnlegen($fix);
            }

            DB::table('products')->where('id', $produktId)->update([
                'is_pantry_staple' => true,
                // Vorhandene Angaben nicht überschreiben: Was am Artikel
                // gepflegt ist, war die bewusstere Entscheidung.
                'pantry_target_amount' => DB::raw('COALESCE(pantry_target_amount, '.$this->wert($fix->amount).')'),
                'default_unit' => DB::raw('COALESCE(default_unit, '.$this->text($fix->unit).')'),
                'product_group_id' => DB::raw('COALESCE(product_group_id, '.$this->wert($fix->product_group_id).')'),
                'updated_at' => now(),
            ]);
        }
    }

    protected function produktZuName(object $fix): ?int
    {
        $normalisiert = $this->normalisieren($fix->raw_name);

        return DB::table('products')
            ->where('family_id', $fix->family_id)
            ->where('name_normalized', $normalisiert)
            ->whereNull('deleted_at')
            ->value('id');
    }

    protected function artikelAnlegen(object $fix): int
    {
        return DB::table('products')->insertGetId([
            'family_id' => $fix->family_id,
            'name' => $fix->raw_name,
            'name_normalized' => $this->normalisieren($fix->raw_name),
            'product_group_id' => $fix->product_group_id,
            'default_unit' => $fix->unit,
            'is_pantry_staple' => true,
            'pantry_target_amount' => $fix->amount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Dieselbe Normalform wie Product::normalize() -- hier ausgeschrieben,
     * damit die Migration unabhaengig vom Model bleibt.
     */
    protected function normalisieren(string $name): string
    {
        $ohneKlammern = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;

        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($ohneKlammern)) ?? $ohneKlammern);
    }

    protected function wert(int|float|string|null $wert): string
    {
        return $wert === null ? 'NULL' : (string) $wert;
    }

    protected function text(?string $wert): string
    {
        return $wert === null ? 'NULL' : DB::getPdo()->quote($wert);
    }
};
