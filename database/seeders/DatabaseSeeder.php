<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user with no profile
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@examsystem.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create sample teachers with profile records via factory
        Teacher::factory(5)->create();

        // Create sample students with profile records via factory
        Student::factory(10)->create();

        $this->call([
            SubjectSeeder::class,
            QuestionSeeder::class,
        ]);
    }
}