<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'System Administrator',
            'email' => 'admin@examsystem.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create sample teacher
        User::create([
            'name' => 'John Doe',
            'email' => 'teacher@examsystem.com',
            'password' => Hash::make('teacher123'),
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        // Create sample student
        User::create([
            'name' => 'Jane Smith',
            'email' => 'student@examsystem.com',
            'password' => Hash::make('student123'),
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        // Create multiple students for testing
        User::factory()->count(10)->create([
            'role' => 'student'
        ]);

        User::factory()->count(5)->create([
            'role' => 'teacher'
        ]);
    }
}
