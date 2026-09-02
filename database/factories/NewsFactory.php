<?php

namespace Database\Factories;

use App\Models\News;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<News>
 */
class NewsFactory extends Factory
{
    protected $model = News::class;

    public function definition(): array
    {
        $body = '<p>'.$this->faker->paragraph().'</p>';

        return [
            'title' => $this->faker->sentence(5),
            'excerpt' => $this->faker->sentence(12),
            'body' => $body,
            'image_path' => null,
            'status' => News::STATUS_DRAFT,
            'audience' => News::AUDIENCE_ALL,
            'pinned' => false,
            'published_at' => null,
            'expires_at' => null,
            'created_by' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => News::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => News::STATUS_DRAFT,
            'published_at' => null,
        ]);
    }

    public function pinned(): static
    {
        return $this->state(fn (): array => [
            'pinned' => true,
        ]);
    }

    public function forRetail(): static
    {
        return $this->state(fn (): array => [
            'audience' => News::AUDIENCE_RETAIL,
        ]);
    }

    public function forCorporate(): static
    {
        return $this->state(fn (): array => [
            'audience' => News::AUDIENCE_CORPORATE,
        ]);
    }
}
