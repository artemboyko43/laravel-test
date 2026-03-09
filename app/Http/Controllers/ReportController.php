<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserRevenueReportRequest;
use App\Http\Resources\UserRevenueResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Get user revenue report with completed orders only
     * 
     * Features:
     * - Filters only completed orders
     * - Supports optional date range filtering
     * - Prevents N+1 queries using withCount and withSum
     * - Includes pagination
     */
    public function userRevenue(UserRevenueReportRequest $request): JsonResponse
    {
        $startDate = $request->validated('start_date');
        $endDate = $request->validated('end_date');
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $query = User::query()
            ->withCount([
                'orders' => function ($query) use ($startDate, $endDate) {
                    $query->where('status', 'completed');
                    if ($startDate && $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    }
                }
            ])
            ->withSum([
                'orders as total_revenue' => function ($query) use ($startDate, $endDate) {
                    $query->where('status', 'completed');
                    if ($startDate && $endDate) {
                        $query->whereBetween('created_at', [$startDate, $endDate]);
                    }
                }
            ], 'total_amount')
            ->having('orders_count', '>', 0)
            ->orderBy('id');

        $users = $query->paginate($perPage);

        return response()->json([
            'data' => UserRevenueResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ],
        ]);
    }
}
