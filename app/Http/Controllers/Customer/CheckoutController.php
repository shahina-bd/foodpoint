<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    /**
     * Place an order from the current cart.
     */
    public function checkout(Request $request)
    {
        $userId = $request->user()->id;

        $cart = Cart::where('user_id', $userId)->with('items.product')->first();

        if (!$cart || $cart->items()->count() === 0) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $validator = Validator::make($request->all(), [
            'address' => 'required|string',
            'phone' => 'required|string',
            'notes' => 'nullable|string',
            'payment_method' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Calculate totals
        $subtotal = 0.00;
        foreach ($cart->items as $item) {
            $subtotal += $item->price * $item->quantity;
        }
        $subtotal = round($subtotal, 2);

        $discount = 0.00;
        if ($cart->coupon_code) {
            $coupon = Coupon::where('code', $cart->coupon_code)->first();
            if ($coupon) {
                $today = now()->startOfDay();
                $expired = $coupon->expiry_date && $coupon->expiry_date->lt($today);
                $wrongRestaurant = $coupon->restaurant_id && $coupon->restaurant_id != $cart->restaurant_id;

                if (!$expired && !$wrongRestaurant) {
                    if ($coupon->discount_type === 'percentage') {
                        $discount = $subtotal * ($coupon->discount / 100);
                    } else {
                        $discount = $coupon->discount;
                    }
                    if ($discount > $subtotal) {
                        $discount = $subtotal;
                    }
                }
            }
        }
        $discount = round($discount, 2);

        $deliveryCharge = 5.00;
        $taxRate = 0.10;
        $tax = ($subtotal - $discount) * $taxRate;
        if ($tax < 0) {
            $tax = 0.00;
        }
        $tax = round($tax, 2);

        $total = ($subtotal - $discount) + $deliveryCharge + $tax;
        $total = round($total, 2);

        // Generate unique order number
        $orderNumber = 'ORD-' . strtoupper(Str::random(10));
        while (Order::where('order_number', $orderNumber)->exists()) {
            $orderNumber = 'ORD-' . strtoupper(Str::random(10));
        }

        // Process order placement
        $order = DB::transaction(function() use ($cart, $request, $orderNumber, $subtotal, $discount, $deliveryCharge, $tax, $total) {
            $order = Order::create([
                'user_id' => $request->user()->id,
                'restaurant_id' => $cart->restaurant_id,
                'order_number' => $orderNumber,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'delivery_charge' => $deliveryCharge,
                'tax' => $tax,
                'total' => $total,
                'payment_status' => 'pending',
                'order_status' => 'pending',
                'address' => $request->address,
                'phone' => $request->phone,
                'notes' => $request->notes,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                ]);

                // Decrement stock
                $item->product->decrement('stock', $item->quantity);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $request->payment_method,
                'amount' => $total,
                'status' => 'pending',
            ]);

            // Clear the cart
            $cart->items()->delete();
            $cart->delete();

            return $order;
        });

        return response()->json([
            'message' => 'Order placed successfully',
            'order' => $order->load(['items.product', 'payment'])
        ], 201);
    }
}
