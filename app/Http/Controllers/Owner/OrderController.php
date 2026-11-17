<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Get restaurant IDs owned by the authenticated user.
     */
    private function getOwnedRestaurantIds($userId)
    {
        return Restaurant::where('owner_id', $userId)->pluck('id')->toArray();
    }

    /**
     * List all orders for the owner's restaurants.
     */
    public function index(Request $request)
    {
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $query = Order::whereIn('restaurant_id', $restaurantIds)->with(['restaurant', 'user']);

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
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $order = Order::whereIn('restaurant_id', $restaurantIds)
                      ->with(['items.product', 'user', 'payment'])
                      ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'order' => $order
        ]);
    }

    /**
     * Accept a pending order.
     */
    public function accept(Request $request, $id)
    {
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $order = Order::whereIn('restaurant_id', $restaurantIds)->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->order_status !== 'pending') {
            return response()->json(['message' => 'Order is not in pending status'], 400);
        }

        $order->update([
            'order_status' => 'accepted'
        ]);

        return response()->json([
            'message' => 'Order accepted successfully',
            'order' => $order
        ]);
    }

    /**
     * Reject a pending order.
     */
    public function reject(Request $request, $id)
    {
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $order = Order::whereIn('restaurant_id', $restaurantIds)->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->order_status !== 'pending' && $order->order_status !== 'accepted') {
            return response()->json(['message' => 'Cannot reject order in this status'], 400);
        }

        $order->update([
            'order_status' => 'cancelled'
        ]);

        return response()->json([
            'message' => 'Order rejected successfully',
            'order' => $order
        ]);
    }

    /**
     * Complete an order.
     */
    public function complete(Request $request, $id)
    {
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $order = Order::whereIn('restaurant_id', $restaurantIds)->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($order->order_status === 'completed' || $order->order_status === 'cancelled') {
            return response()->json(['message' => 'Order is already completed or cancelled'], 400);
        }

        $order->update([
            'order_status' => 'completed',
            'payment_status' => 'completed' // auto mark payment as completed when order is completed
        ]);

        // Update payment record status if it exists
        if ($order->payment) {
            $order->payment->update([
                'status' => 'completed'
            ]);
        }

        return response()->json([
            'message' => 'Order completed successfully',
            'order' => $order
        ]);
    }

    /**
     * Retrieve invoice details for printing.
     */
    public function invoice(Request $request, $id)
    {
        $restaurantIds = $this->getOwnedRestaurantIds($request->user()->id);

        $order = Order::whereIn('restaurant_id', $restaurantIds)
                      ->with(['items.product', 'user', 'restaurant', 'payment'])
                      ->find($id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        return response()->json([
            'invoice' => [
                'order_number' => $order->order_number,
                'date' => $order->created_at->toFormattedDateString(),
                'restaurant' => [
                    'name' => $order->restaurant->name,
                    'phone' => $order->restaurant->phone,
                    'address' => $order->restaurant->address,
                ],
                'customer' => [
                    'name' => $order->user->name,
                    'phone' => $order->phone,
                    'address' => $order->address,
                ],
                'items' => $order->items->map(function($item) {
                    return [
                        'name' => $item->product->name,
                        'price' => (float)$item->price,
                        'quantity' => $item->quantity,
                        'total' => (float)($item->price * $item->quantity),
                    ];
                }),
                'subtotal' => (float)$order->subtotal,
                'discount' => (float)$order->discount,
                'delivery_charge' => (float)$order->delivery_charge,
                'tax' => (float)$order->tax,
                'total' => (float)$order->total,
                'payment_method' => $order->payment ? $order->payment->method : 'N/A',
                'payment_status' => $order->payment_status,
                'order_status' => $order->order_status,
                'notes' => $order->notes,
            ]
        ]);
    }
}
