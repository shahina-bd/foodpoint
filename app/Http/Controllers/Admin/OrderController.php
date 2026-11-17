<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List all orders in the system with optional restaurant and status filters.
     */
    public function index(Request $request)
    {
        $query = Order::with(['restaurant', 'user']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('order_status', $request->status);
        }

        if ($request->has('restaurant_id') && !empty($request->restaurant_id)) {
            $query->where('restaurant_id', $request->restaurant_id);
        }

        $perPage = $request->get('per_page', 15);
        $orders = $query->latest()->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * Retrieve statistics and commission breakdown.
     */
    public function statistics(Request $request)
    {
        $completedOrders = Order::where('order_status', 'completed')->with('restaurant')->get();

        $totalSales = $completedOrders->sum('total');
        
        // 10% flat commission on net subtotal (subtotal - discount)
        $totalCommission = $completedOrders->sum(function($order) {
            return ($order->subtotal - $order->discount) * 0.10;
        });

        // Group by restaurant for breakdown
        $restaurantDetails = [];
        $grouped = $completedOrders->groupBy('restaurant_id');

        foreach ($grouped as $restaurantId => $orders) {
            $restaurant = $orders->first()->restaurant;
            $sales = $orders->sum('total');
            $commission = $orders->sum(function($order) {
                return ($order->subtotal - $order->discount) * 0.10;
            });

            $restaurantDetails[] = [
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurant ? $restaurant->name : 'Unknown Restaurant',
                'orders_count' => $orders->count(),
                'total_sales' => round($sales, 2),
                'commission' => round($commission, 2)
            ];
        }

        $totalOrdersCount = Order::count();
        $activeRestaurantsCount = Restaurant::where('status', 'active')->count();
        $totalUsersCount = User::count();

        return response()->json([
            'statistics' => [
                'total_orders' => $totalOrdersCount,
                'total_sales' => round($totalSales, 2),
                'total_commission' => round($totalCommission, 2),
                'active_restaurants' => $activeRestaurantsCount,
                'total_users' => $totalUsersCount,
                'restaurant_breakdown' => $restaurantDetails
            ]
        ]);
    }
}
