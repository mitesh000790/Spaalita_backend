<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Throwable;

class DashboardController extends Controller
{
    /**
     * GET /api/dashboard
     */
    public function __invoke(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard data fetched successfully',
                'data' => [
                    'totalUsers' => User::query()->count(),
                    'activeUsers' => User::query()->where('status', 'active')->count(),
                    'totalOrders' => Order::query()->count(),
                    'completedOrders' => Order::query()->where('status', 'completed')->count(),
                    'pendingOrders' => Order::query()->where('status', 'pending')->count(),
                    'cancelledOrders' => Order::query()->where('status', 'cancelled')->count(),
                    'totalProducts' => Product::query()->count(),
                    'totalGiftCards' => GiftCard::query()->count(),
                ],
            ]);
        } catch (Throwable $exception) {
            return $this->serverError('Failed to fetch dashboard data', $exception, ['data' => null]);
        }
    }
}
