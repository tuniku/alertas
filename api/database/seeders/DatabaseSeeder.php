<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@alertas.local'],
            [
                'name' => 'Administrador',
                'password' => 'admin123', // cast 'hashed' aplica o bcrypt
            ]
        );
    }
}
