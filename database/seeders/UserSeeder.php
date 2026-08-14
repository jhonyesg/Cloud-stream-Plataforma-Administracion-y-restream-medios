<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@cloudstream.local'],
            [
                'id' => '11111111-1111-1111-1111-111111111111',
                'username' => 'admin',
                'display_name' => 'Administrador',
                'password' => Hash::make('admin12345'),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'owner_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'cliente@cloudstream.local'],
            [
                'id' => '22222222-2222-2222-2222-222222222222',
                'username' => 'cliente',
                'display_name' => 'Cliente Demo',
                'password' => Hash::make('cliente12345'),
                'role' => 'client',
                'status' => 'active',
                'email_verified_at' => now(),
                'owner_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'cinedios@cloudstream.local'],
            [
                'username' => 'cinedios',
                'display_name' => 'Cine Dios',
                'password' => Hash::make('cinedios12345'),
                'role' => 'client',
                'status' => 'active',
                'email_verified_at' => now(),
                'owner_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'redplanet@redplanet.com'],
            [
                'username' => 'redplanet',
                'display_name' => 'Red Planet',
                'password' => Hash::make('redplanet12345'),
                'role' => 'client',
                'status' => 'active',
                'email_verified_at' => now(),
                'owner_id' => null,
            ]
        );
    }
}