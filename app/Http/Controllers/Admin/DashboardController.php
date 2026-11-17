<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Get admin dashboard analytics.
     */
    public function index(Request $request)
    {
        $totalRestaurants = Restaurant::count();

        // Customers count (role is customer or default user)
        $customersCount = User::whereIn('role', ['customer', 'user'])->count();

        // Revenue (sum of total for completed orders)
        $revenue = Order::where('order_status', 'completed')->sum('total');

        // Orders placed today
        $ordersToday = Order::whereDate('created_at', Carbon::today())->count();

        // Sales chart (daily sales for the last 30 days)
        $salesChart = Order::where('order_status', 'completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->selectRaw("DATE(created_at) as date, SUM(total) as sales")
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function($item) {
                return [
                    'date' => $item->date,
                    'sales' => round((float)$item->sales, 2)
                ];
            });

        // Top Restaurants (top 5 by completed sales)
        $topRestaurants = Restaurant::select('id', 'name', 'logo')
            ->selectRaw('COALESCE((SELECT SUM(total) FROM orders WHERE orders.restaurant_id = restaurants.id AND orders.order_status = "completed"), 0) as total_sales')
            ->selectRaw('(SELECT COUNT(*) FROM orders WHERE orders.restaurant_id = restaurants.id) as total_orders')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get()
            ->filter(function($restaurant) {
                return $restaurant->total_sales > 0;
            })
            ->values()
            ->map(function($restaurant) {
                return [
                    'id' => $restaurant->id,
                    'name' => $restaurant->name,
                    'logo' => $restaurant->logo,
                    'total_sales' => round((float)$restaurant->total_sales, 2),
                    'total_orders' => (int)$restaurant->total_orders
                ];
            });

        return response()->json([
            'total_restaurants' => $totalRestaurants,
            'customers' => $customersCount,
            'revenue' => round((float)$revenue, 2),
            'orders_today' => $ordersToday,
            'sales_chart' => $salesChart,
            'top_restaurants' => $topRestaurants
        ]);
    }
}
