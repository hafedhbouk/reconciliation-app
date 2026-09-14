<?php

namespace Database\Seeders;

/**
 * Seed de l'utilisateur administrateur par défaut.
 *
 * Crée l'utilisateur administrateur initial sans écraser le mot de passe
 * d'un compte déjà installé, puis garantit son rôle super-admin.
 */
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@reconciliation.local'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $admin->assignRole('super-admin');
    }
}
