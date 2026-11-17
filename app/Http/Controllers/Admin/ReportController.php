<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Daily, Monthly, and Yearly Sales Reports.
     */
    public function sales(Request $request)
    {
        $groupBy = $request->get('group_by', 'daily'); // daily, monthly, yearly

        $driver = DB::connection()->getDriverName();

        if ($groupBy === 'yearly') {
            $selectPeriod = $driver === 'sqlite' ? "strftime('%Y', created_at) as period" : "DATE_FORMAT(created_at, '%Y') as period";
        } elseif ($groupBy === 'monthly') {
            $selectPeriod = $driver === 'sqlite' ? "strftime('%Y-%m', created_at) as period" : "DATE_FORMAT(created_at, '%Y-%m') as period";
        } else {
            // default is daily
            $selectPeriod = "DATE(created_at) as period";
        }

        $sales = Order::where('order_status', 'completed')
            ->selectRaw($selectPeriod)
            ->selectRaw("COUNT(*) as orders_count")
            ->selectRaw("SUM(total) as total_sales")
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'period' => $item->period,
                    'orders_count' => (int)$item->orders_count,
                    'total_sales' => round((float)$item->total_sales, 2)
                ];
            });

        return response()->json($sales);
    }

    /**
     * Restaurant Earnings Report.
     */
    public function restaurantEarnings(Request $request)
    {
        $orders = Order::where('order_status', 'completed')->with('restaurant')->get();

        $breakdown = $orders->groupBy('restaurant_id')->map(function($orders, $restaurantId) {
            $restaurant = $orders->first()->restaurant;
            $sales = $orders->sum('total');
            $commission = $orders->sum(function($order) {
                return ($order->subtotal - $order->discount) * 0.10;
            });
            $earnings = $sales - $commission;

            return [
                'restaurant_id' => $restaurantId,
                'restaurant_name' => $restaurant ? $restaurant->name : 'Unknown Restaurant',
                'orders_count' => $orders->count(),
                'total_sales' => round($sales, 2),
                'commission' => round($commission, 2),
                'earnings' => round($earnings, 2)
            ];
        })->values();

        return response()->json($breakdown);
    }

    /**
     * Commission Report.
     */
    public function commissions(Request $request)
    {
        $driver = DB::connection()->getDriverName();
        $selectPeriod = $driver === 'sqlite' ? "strftime('%Y-%m', created_at) as period" : "DATE_FORMAT(created_at, '%Y-%m') as period";

        $commissions = Order::where('order_status', 'completed')
            ->selectRaw($selectPeriod)
            ->selectRaw("SUM((subtotal - discount) * 0.10) as commission")
            ->selectRaw("COUNT(*) as orders_count")
            ->groupBy('period')
            ->orderBy('period', 'desc')
            ->get()
            ->map(function($item) {
                return [
                    'period' => $item->period,
                    'orders_count' => (int)$item->orders_count,
                    'commission' => round((float)$item->commission, 2)
                ];
            });

        return response()->json($commissions);
    }

    /**
     * Customer Report.
     */
    public function customers(Request $request)
    {
        $customers = User::whereIn('role', ['customer', 'user'])
            ->select('users.id', 'users.name', 'users.email')
            ->selectRaw('COALESCE((SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id AND orders.order_status = "completed"), 0) as completed_orders_count')
            ->selectRaw('COALESCE((SELECT SUM(total) FROM orders WHERE orders.user_id = users.id AND orders.order_status = "completed"), 0) as total_spent')
            ->get()
            ->map(function($user) {
                $spent = (float)$user->total_spent;
                $count = (int)$user->completed_orders_count;
                $aov = $count > 0 ? $spent / $count : 0.00;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'orders_count' => $count,
                    'total_spent' => round($spent, 2),
                    'average_order_value' => round($aov, 2)
                ];
            })
            ->sortByDesc('total_spent')
            ->values();

        return response()->json($customers);
    }
}
