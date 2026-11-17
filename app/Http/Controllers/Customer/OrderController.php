<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * List all orders of the authenticated customer with optional status filtering.
     */
    public function index(Request $request)
    {
        $query = Order::where('user_id', $request->user()->id)->with(['restaurant']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('order_status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $orders = $query->latest()->paginate($perPage);

        return response()->json($orders);
    }

    /**
     * View a single order.
     */
    public function show(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)
                      ->with(['items.product', 'restaurant', 'payment'])
                      ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => $order
        ]);
    }

    /**
     * Cancel a pending order.
     */
    public function cancel(Request $request, $id)
    {
        $order = Order::where('user_id', $request->user()->id)->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->order_status !== 'pending') {
            return response()->json(['message' => 'Only pending orders can be cancelled'], 400);
        }

        $order->update([
            'order_status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Order cancelled successfully',
            'order' => $order
        ]);
    }
}
