<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseEngagement;
use App\Models\SubscriptionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseEngagement>
 */
class CourseEngagementFactory extends Factory
{
    protected $model = CourseEngagement::class;

    public function definition(): array
    {
        $course = Course::inRandomOrder()->first() ?? Course::factory()->create();

        return [
            'subscription_period_id' => SubscriptionPeriod::factory(),
            'student_id' => User::factory()->student(),
            'instructor_id' => $course->instructor_id,
            'course_id' => $course->id,
            'seconds_watched' => fake()->numberBetween(180, 5400),
        ];
    }
}
