<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'instructor_id' => User::factory()->instructor(),
            'title' => fake()->randomElement([
                'Advanced Laravel Architecture & Best Practices',
                'Building Enterprise Financial Ledgers in PHP',
                'High-Concurrency Job Queues & Redis',
                'Modern Frontend Development with Livewire & Filament',
                'Mastering Domain-Driven Design and Clean Architecture',
                'Distributed Systems, Idempotency & Fault Tolerance',
            ]),
        ];
    }
}
