<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get owner dashboard analytics.
     */
    public function index(Request $request)
    {
        $ownerId = $request->user()->id;
        $ownedRestaurantIds = Restaurant::where('owner_id', $ownerId)->pluck('id')->toArray();

        if (empty($ownedRestaurantIds)) {
            return response()->json([
                'today_sales' => 0.00,
                'weekly_sales' => 0.00,
                'monthly_sales' => 0.00,
                'popular_foods' => [],
                'pending_orders' => [
                    'count' => 0,
                    'list' => []
                ]
            ]);
        }

        // Optional filter by restaurant_id
        if ($request->has('restaurant_id') && !empty($request->restaurant_id)) {
            $restaurantId = $request->restaurant_id;
            if (!in_array($restaurantId, $ownedRestaurantIds)) {
                return response()->json(['message' => 'Unauthorized restaurant access'], 403);
            }
            $targetRestaurantIds = [$restaurantId];
        } else {
            $targetRestaurantIds = $ownedRestaurantIds;
        }

        // Today's Sales
        $todaySales = Order::whereIn('restaurant_id', $targetRestaurantIds)
            ->where('order_status', 'completed')
            ->whereDate('created_at', Carbon::today())
            ->sum('total');

        // Weekly Sales (last 7 days)
        $weeklySales = Order::whereIn('restaurant_id', $targetRestaurantIds)
            ->where('order_status', 'completed')
            ->where('created_at', '>=', now()->subDays(7))
            ->sum('total');

        // Monthly Sales (last 30 days)
        $monthlySales = Order::whereIn('restaurant_id', $targetRestaurantIds)
            ->where('order_status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('total');

        // Popular Foods (top 5 most ordered products)
        $popularFoods = OrderItem::whereHas('order', function($q) use ($targetRestaurantIds) {
                $q->whereIn('restaurant_id', $targetRestaurantIds)->where('order_status', 'completed');
            })
            ->select('product_id')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('COUNT(*) as order_count')
            ->with('product:id,name,image')
            ->groupBy('product_id')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get()
            ->map(function($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product ? $item->product->name : 'Unknown Product',
                    'image' => $item->product ? $item->product->image : null,
                    'total_quantity' => (int)$item->total_quantity,
                    'order_count' => (int)$item->order_count
                ];
            });

        // Pending Orders
        $pendingCount = Order::whereIn('restaurant_id', $targetRestaurantIds)
            ->where('order_status', 'pending')
            ->count();

        $pendingList = Order::whereIn('restaurant_id', $targetRestaurantIds)
            ->where('order_status', 'pending')
            ->with('user:id,name,email')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'today_sales' => round((float)$todaySales, 2),
            'weekly_sales' => round((float)$weeklySales, 2),
            'monthly_sales' => round((float)$monthlySales, 2),
            'popular_foods' => $popularFoods,
            'pending_orders' => [
                'count' => $pendingCount,
                'list' => $pendingList
            ]
        ]);
    }
}
