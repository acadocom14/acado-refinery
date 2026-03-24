<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Erstellt den User nur, wenn die E-Mail noch nicht existiert
        User::updateOrCreate(
            ['email' => 'ceo@acado.test'],
            [
                'name' => 'Peter',
                'password' => bcrypt('password'),
            ]
        );

        $this->call([
            AgentSeeder::class,
        ]);
    }
}
