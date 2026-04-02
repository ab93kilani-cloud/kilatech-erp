<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Création des Rôles
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $gerantRole = Role::firstOrCreate(['name' => 'Gérant']);
        $salesRole = Role::firstOrCreate(['name' => 'Commercial']);
        $purchasingRole = Role::firstOrCreate(['name' => 'Responsable d\'Achat']);
        $accountingRole = Role::firstOrCreate(['name' => 'Comptable']);
        $marketingRole = Role::firstOrCreate(['name' => 'Responsable Marketing']);
        
        // On conserve le rôle logistique s'il y a de l'historique ou pour le futur
        $logisticsRole = Role::firstOrCreate(['name' => 'Logisticien']);

        // Création de l'Administrateur par défaut
        User::firstOrCreate(
            ['email' => 'admin@kilatech.com'],
            [
                'name' => 'Administrateur Principal',
                'password_hash' => Hash::make('Admin!123'),
                'role_id' => $adminRole->id,
            ]
        );

        // Création d'un Gérant de test
        User::firstOrCreate(
            ['email' => 'gerant@kilatech.com'],
            [
                'name' => 'Directeur Gérant',
                'password_hash' => Hash::make('Gerant!123'),
                'role_id' => $gerantRole->id,
            ]
        );

        // Création d'un Commercial de test
        User::firstOrCreate(
            ['email' => 'sales@kilatech.com'],
            [
                'name' => 'Commercial Test',
                'password_hash' => Hash::make('Sales!123'),
                'role_id' => $salesRole->id,
            ]
        );

        // Création des autres profils de test
        User::firstOrCreate(
            ['email' => 'achat@kilatech.com'],
            [
                'name' => 'Responsable Achats',
                'password_hash' => Hash::make('Achat!123'),
                'role_id' => $purchasingRole->id,
            ]
        );

        User::firstOrCreate(
            ['email' => 'compta@kilatech.com'],
            [
                'name' => 'Service Comptabilité',
                'password_hash' => Hash::make('Compta!123'),
                'role_id' => $accountingRole->id,
            ]
        );
    }
}
