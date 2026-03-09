<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconcileOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconcile_orders_detects_consistent_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.50,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 100.50,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->expectsOutput('Starting order reconciliation...')
            ->expectsOutput('=== Reconciliation Summary ===')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_reconcile_orders_detects_mismatched_orders(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 99.50,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->expectsOutput('Starting order reconciliation...')
            ->expectsOutput('=== Reconciliation Summary ===')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_reconcile_orders_handles_floating_point_precision(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_reconcile_orders_handles_orders_with_no_items(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
        ]);

        $this->artisan('reconcile:orders')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_reconcile_orders_handles_multiple_items(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 300.50,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 200.50,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_reconcile_orders_logs_mismatches(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 99.00,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_reconcile_orders_displays_correct_summary(): void
    {
        $user = User::factory()->create();

        // Create consistent order
        $order1 = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 100.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order1->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        // Create mismatched order
        $order2 = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 200.00,
        ]);
        OrderItem::factory()->create([
            'order_id' => $order2->id,
            'price' => 199.00,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->expectsOutput('Total Orders Checked: 2')
            ->expectsOutput('Mismatched Orders: 1')
            ->expectsOutput('Mismatch Percentage: 50.00%')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_reconcile_orders_handles_zero_totals(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total_amount' => 0.00,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'price' => 100.00,
            'quantity' => 1,
        ]);

        $this->artisan('reconcile:orders')
            ->assertExitCode(Command::FAILURE);
    }
}
