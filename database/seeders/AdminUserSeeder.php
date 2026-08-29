<?php

namespace Database\seeders;

/**
 * Seed de l'utilisateur administrateur par défaut.
 *
 * Crée ou met à jour l'utilisateur admin@reconciliation.local et lui
 * attribue le rôle super-admin. Idempotent grâce à updateOrCreate.
 */
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
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
