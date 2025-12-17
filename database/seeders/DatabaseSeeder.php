<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use function bcrypt;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Anh Nguyễn', 'email' => 'anh.nguyen@example.com'],
            ['name' => 'Bình Trần', 'email' => 'binh.tran@example.com'],
            ['name' => 'Chi Phạm', 'email' => 'chi.pham@example.com'],
        ];

        foreach ($users as $user) {
            User::factory()->create([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => bcrypt('password'),
            ]);
        }

        $this->call([
            MessageSeeder::class,
        ]);
    }
}
