<?php

namespace Database\Seeders;

use App\Core\Models\User;
use App\Core\Services\FamilyProvisioner;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Bewusst ohne WithoutModelEvents: die public_id wird im creating-Hook
     * vergeben, ohne Events blieben die Spalten leer.
     */
    public function run(FamilyProvisioner $provisioner): void
    {
        if (! User::where('email', 'test@example.com')->exists()) {
            $user = User::factory()->create([
                'name' => 'Markus',
                'email' => 'test@example.com',
            ]);

            $provisioner->createFor($user, 'Testfamilie');
        }

        // Eine Familie mit Alltagsdaten: Katalog, Fixartikel, laufende Liste
        // und Archiv. Laesst sich auch einzeln aufrufen:
        //   php artisan db:seed --class=DemoDataSeeder
        $this->call(DemoDataSeeder::class);
    }
}
