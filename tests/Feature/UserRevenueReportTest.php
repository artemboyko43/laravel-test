<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_revenue_report_returns_completed_orders_only(): void
    {
        $user = User::factory()->create();

        // Create completed order
        $completedOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => 100.50,
        ]);

        OrderItem::factory()->create([
            'order_id' => $completedOrder->id,
            'price' => 100.50,
            'quantity' => 1,
        ]);

        // Create pending order (should not be included)
        $pendingOrder = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'total_amount' => 50.00,
        ]);

        $response = $this->getJson('/api/reports/user-revenue');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['user_id', 'email', 'orders_count', 'total_revenue'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $response->assertJson([
            'data' => [
                [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'orders_count' => 1,
                    'total_revenue' => 100.50,
                ],
            ],
        ]);
    }

    public function test_user_revenue_report_filters_by_date_range(): void
    {
        $user = User::factory()->create();

        // Order within date range
        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => 100.00,
            'created_at' => '2024-01-15 10:00:00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        // Order outside date range (should not be included)
        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => 200.00,
            'created_at' => '2024-02-15 10:00:00',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'price' => 200.00,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/reports/user-revenue?start_date=2024-01-01&end_date=2024-01-31');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'user_id' => $user->id,
                        'orders_count' => 1,
                        'total_revenue' => 100.00,
                    ],
                ],
            ]);
    }

    public function test_user_revenue_report_supports_pagination(): void
    {
        $users = User::factory()->count(25)->create();

        foreach ($users as $user) {
            $order = Order::factory()->create([
                'user_id' => $user->id,
                'status' => 'completed',
                'total_amount' => 50.00,
            ]);

            OrderItem::factory()->create([
                'order_id' => $order->id,
                'price' => 50.00,
                'quantity' => 1,
            ]);
        }

        $response = $this->getJson('/api/reports/user-revenue?page=1&per_page=10');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 25,
                    'last_page' => 3,
                ],
            ])
            ->assertJsonCount(10, 'data');
    }

    public function test_user_revenue_report_validates_date_format(): void
    {
        $response = $this->getJson('/api/reports/user-revenue?start_date=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date']);
    }

    public function test_user_revenue_report_validates_end_date_after_start_date(): void
    {
        $response = $this->getJson('/api/reports/user-revenue?start_date=2024-01-31&end_date=2024-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_user_revenue_report_validates_per_page_limit(): void
    {
        $response = $this->getJson('/api/reports/user-revenue?per_page=150');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_user_revenue_report_excludes_users_with_no_completed_orders(): void
    {
        $userWithCompleted = User::factory()->create();
        $userWithPending = User::factory()->create();

        Order::factory()->create([
            'user_id' => $userWithCompleted->id,
            'status' => 'completed',
            'total_amount' => 100.00,
        ]);

        Order::factory()->create([
            'user_id' => $userWithPending->id,
            'status' => 'pending',
            'total_amount' => 50.00,
        ]);

        $response = $this->getJson('/api/reports/user-revenue');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJson([
                'data' => [
                    [
                        'user_id' => $userWithCompleted->id,
                    ],
                ],
            ]);
    }

    public function test_user_revenue_report_calculates_total_revenue_correctly(): void
    {
        $user = User::factory()->create();

        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => 100.00,
        ]);

        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'completed',
            'total_amount' => 200.50,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'price' => 200.50,
            'quantity' => 1,
        ]);

        $response = $this->getJson('/api/reports/user-revenue');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    [
                        'user_id' => $user->id,
                        'orders_count' => 2,
                        'total_revenue' => 300.50,
                    ],
                ],
            ]);
    }
}
