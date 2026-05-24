<?php

namespace Database\Factories;

use App\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

class WebhookFactory extends Factory
{
    protected $model = Webhook::class;

    public function definition(): array
    {
        return [
            'name' => 'Test Webhook ' . $this->faker->unique()->numberBetween(1, 9999),
            'url' => 'https://example.com/webhook',
            'events' => ['sale.created'],
            'secret' => bin2hex(random_bytes(16)),
            'is_active' => true,
        ];
    }
}