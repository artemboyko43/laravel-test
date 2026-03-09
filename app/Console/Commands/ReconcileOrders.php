<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileOrders extends Command
{
    protected $signature = 'reconcile:orders';

    protected $description = 'Reconcile order totals with calculated totals from order items';

    private const TOLERANCE = 0.01;

    public function handle(): int
    {
        $this->info('Starting order reconciliation...');

        $totalChecked = 0;
        $mismatchedCount = 0;
        $errors = 0;

        try {
            foreach (Order::with('orderItems')->cursor() as $order) {
                try {
                    $totalChecked++;

                    if ($order->orderItems->isEmpty()) {
                        $this->handleEmptyOrderItems($order);

                        continue;
                    }

                    $calculatedTotal = $this->calculateTotal($order);

                    if ($order->total_amount === null) {
                        $this->handleNullTotal($order, $calculatedTotal);
                        $mismatchedCount++;

                        continue;
                    }

                    if ($this->isMismatched($order->total_amount, $calculatedTotal)) {
                        $mismatchedCount++;
                        $this->logMismatch($order, $calculatedTotal);
                    }
                } catch (\Exception $e) {
                    $errors++;
                    $this->handleError($order, $e);
                }
            }
        } catch (\Exception $e) {
            $this->error('Fatal error during reconciliation: '.$e->getMessage());
            Log::error('Fatal reconciliation error', ['error' => $e->getMessage()]);

            return Command::FAILURE;
        }

        $this->displaySummary($totalChecked, $mismatchedCount, $errors);

        return ($mismatchedCount > 0 || $errors > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    private function calculateTotal(Order $order): float
    {
        return $order->orderItems->sum(function ($item) {
            $price = $item->price ?? 0;
            $quantity = $item->quantity ?? 0;

            if ($price < 0 || $quantity < 0) {
                throw new \InvalidArgumentException("Invalid price or quantity for order item #{$item->id}");
            }

            return $price * $quantity;
        });
    }

    private function isMismatched(float $stored, float $calculated): bool
    {
        return abs($stored - $calculated) > self::TOLERANCE;
    }

    private function logMismatch(Order $order, float $calculatedTotal): void
    {
        $difference = abs($order->total_amount - $calculatedTotal);

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

    private function handleEmptyOrderItems(Order $order): void
    {
        $this->warn(sprintf(
            'Order #%d: No order items found. Stored total: %.2f',
            $order->id,
            $order->total_amount ?? 0
        ));
    }

    private function handleNullTotal(Order $order, float $calculatedTotal): void
    {
        $this->warn(sprintf(
            'Order #%d: Stored total is null. Calculated total: %.2f',
            $order->id,
            $calculatedTotal
        ));
    }

    private function handleError(Order $order, \Exception $e): void
    {
        $this->error(sprintf(
            'Error processing Order #%d: %s',
            $order->id ?? 'unknown',
            $e->getMessage()
        ));

        Log::error('Order reconciliation error', [
            'order_id' => $order->id ?? null,
            'error' => $e->getMessage(),
        ]);
    }

    private function displaySummary(int $totalChecked, int $mismatchedCount, int $errors): void
    {
        $this->newLine();
        $this->info('=== Reconciliation Summary ===');
        $this->info(sprintf('Total Orders Checked: %s', number_format($totalChecked)));
        $this->info(sprintf('Mismatched Orders: %s', number_format($mismatchedCount)));

        if ($errors > 0) {
            $this->warn(sprintf('Errors Encountered: %s', number_format($errors)));
        }

        $percentage = $totalChecked > 0 ? ($mismatchedCount / $totalChecked) * 100 : 0;
        $this->info(sprintf('Mismatch Percentage: %.2f%%', $percentage));
    }
}
