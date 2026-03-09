<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderItemFactory extends Factory
{
    protected $model = \App\Models\OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'price' => fake()->randomFloat(2, 5, 500),
            'quantity' => fake()->numberBetween(1, 10),
        ];
    }
}
