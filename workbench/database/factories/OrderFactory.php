<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Workbench\App\Models\Order;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Order>
     */
    protected $model = Order::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => 1,
            'status' => 'open',
            'total' => 100,
            'notes' => null,
            'code' => fake()->unique()->bothify('????##'),
            'is_priority' => false,
            'placed_at' => '2026-01-15 12:00:00',
            'internal_reference' => fake()->uuid(),
        ];
    }
}
