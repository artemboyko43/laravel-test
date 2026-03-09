<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileOrders extends Command
{
    protected $signature = 'reconcile:orders';

    protected $description = 'Reconcile order totals with calculated totals from order items';

    /**
     * Execute the reconciliation command
     * 
     * Checks if stored order.total_amount matches sum(order_items.price * quantity)
     * Logs inconsistencies and provides summary
     */
    public function handle(): int
    {
        $this->info('Starting order reconciliation...');
        
        $totalChecked = 0;
        $mismatchedCount = 0;
        $errors = 0;

        try {
            // Use cursor for memory efficiency with large datasets
            foreach (Order::with('orderItems')->cursor() as $order) {
                try {
                    $totalChecked++;

                    // Handle orders with no items
                    if ($order->orderItems->isEmpty()) {
                        $this->warn(sprintf(
                            'Order #%d: No order items found. Stored total: %.2f',
                            $order->id,
                            $order->total_amount
                        ));
                        continue;
                    }

                    // Calculate expected total with null safety
                    $calculatedTotal = $order->orderItems->sum(function ($item) {
                        $price = $item->price ?? 0;
                        $quantity = $item->quantity ?? 0;
                        
                        if ($price < 0 || $quantity < 0) {
                            throw new \Exception("Invalid price or quantity for order item #{$item->id}");
                        }
                        
                        return $price * $quantity;
                    });

                    // Validate stored total
                    if ($order->total_amount === null) {
                        $this->warn(sprintf(
                            'Order #%d: Stored total is null. Calculated total: %.2f',
                            $order->id,
                            $calculatedTotal
                        ));
                        $mismatchedCount++;
                        continue;
                    }

                    // Use tolerance for floating point comparison (0.01)
                    $difference = abs($order->total_amount - $calculatedTotal);
                    
                    if ($difference > 0.01) {
                        $mismatchedCount++;

                        // Log each inconsistency
                        $this->warn(sprintf(
                            'Order #%d: Stored=%.2f, Calculated=%.2f, Difference=%.2f',
                            $order->id,
                            $order->total_amount,
                            $calculatedTotal,
                            $difference
                        ));

                        Log::warning('Order reconciliation mismatch', [
                            'order_id' => $order->id,
                            'stored_total' => $order->total_amount,
                            'calculated_total' => $calculatedTotal,
                            'difference' => $difference,
                        ]);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->error(sprintf(
                        'Error processing Order #%d: %s',
                        $order->id ?? 'unknown',
                        $e->getMessage()
                    ));
                    
                    Log::error('Order reconciliation error', [
                        'order_id' => $order->id ?? null,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            $this->error('Fatal error during reconciliation: ' . $e->getMessage());
            Log::error('Fatal reconciliation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return Command::FAILURE;
        }

        // Display summary
        $this->newLine();
        $this->info('=== Reconciliation Summary ===');
        $this->info(sprintf('Total Orders Checked: %s', number_format($totalChecked)));
        $this->info(sprintf('Mismatched Orders: %s', number_format($mismatchedCount)));
        
        if ($errors > 0) {
            $this->warn(sprintf('Errors Encountered: %s', number_format($errors)));
        }
        
        if ($totalChecked > 0) {
            $percentage = ($mismatchedCount / $totalChecked) * 100;
            $this->info(sprintf('Mismatch Percentage: %.2f%%', $percentage));
        } else {
            $this->info('Mismatch Percentage: 0.00%');
        }

        // Return failure if there are mismatches or errors
        return ($mismatchedCount > 0 || $errors > 0) ? Command::FAILURE : Command::SUCCESS;
    }
}
