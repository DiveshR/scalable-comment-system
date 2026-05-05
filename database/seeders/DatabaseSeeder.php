<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Default Admin
        $admin = User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@admin.com',
            'password' => 'password',
            'is_admin' => true,
            'is_active' => true,
        ]);

        // 2. Create Default User
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'user@user.com',
            'password' => 'password',
            'is_admin' => false,
            'is_active' => true,
        ]);

        // 3. Create 10 dummy posts for the user
        $titles = [
            'How to Build Scalable Systems',
            'Understanding Nested Comments',
            'Laravel 13 Performance Tips',
            'The Beauty of Filament v4',
            'Modern Web Design Principles',
            'Database Optimization Techniques',
            'Building Real-time Applications',
            'Exploring the New PHP 8.3 Features',
            'Security Best Practices for Web Apps',
            'The Future of Full-stack Development'
        ];

        foreach ($titles as $index => $title) {
            Post::create([
                'user_id' => $user->id,
                'title' => $title,
                'body' => "This is a detailed post about $title. It contains valuable information for anyone looking to improve their development skills.",
                'is_visible' => true,
            ]);
        }

        // 4. Create some posts for the admin
        Post::create([
            'user_id' => $admin->id,
            'title' => 'Official Platform Update v1.0',
            'body' => 'Welcome to the official launch of our scalable comment system! Check out the new features and start discussing.',
            'is_visible' => true,
        ]);
    }
}
