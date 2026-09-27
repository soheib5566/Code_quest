<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InstructorSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = [
            [
                'name' => 'Taylor Otwell',
                'email' => 'taylor@laravel.test',
                'iban_account' => 'US89370400440532013000',
                'courses' => [
                    'Mastering Laravel Architecture & Service Providers',
                    'High-Concurrency Job Queues & Redis Processing',
                ],
            ],
            [
                'name' => 'Jeffrey Way',
                'email' => 'jeffrey@laracasts.test',
                'iban_account' => 'US44123456789012345678',
                'courses' => [
                    'Financial Ledgers & Double-Entry Accounting in PHP',
                    'Object-Oriented Design & Clean Code Practices',
                ],
            ],
            [
                'name' => 'Adam Wathan',
                'email' => 'adam@tailwind.test',
                'iban_account' => 'US55987654321098765432',
                'courses' => [
                    'Modern Full-Stack Architecture with Inertia & Tailwind',
                ],
            ],
            [
                'name' => 'Dan Harrin',
                'email' => 'dan@filament.test',
                'iban_account' => 'GB29NWBK60161331926819',
                'courses' => [
                    'Building Enterprise Admin Dashboards with Filament v5',
                ],
            ],
        ];

        foreach ($instructors as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password'),
                    'role' => Role::INSTRUCTOR,
                    'iban_account' => $data['iban_account'],
                ]
            );

            foreach ($data['courses'] as $title) {
                Course::firstOrCreate(
                    [
                        'instructor_id' => $user->id,
                        'title' => $title,
                    ]
                );
            }
        }
    }
}
