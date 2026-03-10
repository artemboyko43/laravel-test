<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_endpoint_returns_users_with_order_counts(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Order::factory()->count(3)->create(['user_id' => $user1->id]);
        Order::factory()->count(5)->create(['user_id' => $user2->id]);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'orders_count'],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);

        $user1Data = collect($data)->firstWhere('id', $user1->id);
        $this->assertEquals(3, $user1Data['orders_count']);

        $user2Data = collect($data)->firstWhere('id', $user2->id);
        $this->assertEquals(5, $user2Data['orders_count']);
    }

    public function test_users_endpoint_prevents_n_plus_one_queries(): void
    {
        User::factory()->count(10)->create()->each(function ($user) {
            Order::factory()->count(2)->create(['user_id' => $user->id]);
        });

        DB::enableQueryLog();

        $this->getJson('/api/users');

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Should be 2 queries: 1 for users with count, 1 for pagination count
        $this->assertLessThanOrEqual(3, $queryCount, 'N+1 query problem detected!');
    }

    public function test_users_endpoint_supports_pagination(): void
    {
        User::factory()->count(25)->create();

        $response = $this->getJson('/api/users?per_page=10&page=1');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 1,
                    'per_page' => 10,
                    'total' => 25,
                    'last_page' => 3,
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_users_endpoint_pagination_page_2(): void
    {
        User::factory()->count(25)->create();

        $response = $this->getJson('/api/users?per_page=10&page=2');

        $response->assertStatus(200)
            ->assertJson([
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 10,
                ],
            ]);

        $this->assertCount(10, $response->json('data'));
    }

    public function test_users_endpoint_validates_per_page_maximum(): void
    {
        $response = $this->getJson('/api/users?per_page=200');

        $response->assertStatus(422);
    }

    public function test_users_endpoint_validates_per_page_minimum(): void
    {
        $response = $this->getJson('/api/users?per_page=0');

        $response->assertStatus(422);
    }

    public function test_users_endpoint_validates_page_minimum(): void
    {
        $response = $this->getJson('/api/users?page=0');

        $response->assertStatus(422);
    }

    public function test_users_endpoint_handles_empty_results(): void
    {
        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [],
                'meta' => [
                    'total' => 0,
                    'current_page' => 1,
                ],
            ]);
    }

    public function test_users_endpoint_returns_zero_orders_count_when_no_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->getJson('/api/users');

        $userData = collect($response->json('data'))->firstWhere('id', $user->id);
        $this->assertEquals(0, $userData['orders_count']);
    }

    public function test_users_endpoint_defaults_to_15_per_page(): void
    {
        User::factory()->count(20)->create();

        $response = $this->getJson('/api/users');

        $response->assertJson([
            'meta' => [
                'per_page' => 15,
            ],
        ]);

        $this->assertCount(15, $response->json('data'));
    }
}
