<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    /**
     * Helper to build a comprehensive cart response with calculated totals.
     */
    private function getCartResponse($cart)
    {
        if (!$cart) {
            return [
                'cart' => null,
                'items' => [],
                'subtotal' => 0.00,
                'discount' => 0.00,
                'delivery_charge' => 0.00,
                'tax' => 0.00,
                'total' => 0.00,
            ];
        }

        $cart->load(['items.product', 'restaurant']);

        $subtotal = 0.00;
        $itemsData = [];

        foreach ($cart->items as $item) {
            $product = $item->product;
            $itemTotal = $item->price * $item->quantity;
            $subtotal += $itemTotal;

            $itemsData[] = [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $product->name,
                'image' => $product->image,
                'quantity' => $item->quantity,
                'price' => (float)$item->price,
                'total' => (float)$itemTotal,
            ];
        }

        // Coupon calculation
        $discount = 0.00;
        if ($cart->coupon_code) {
            $coupon = Coupon::where('code', $cart->coupon_code)->first();
            if ($coupon) {
                $today = now()->startOfDay();
                $expired = $coupon->expiry_date && $coupon->expiry_date->lt($today);
                $wrongRestaurant = $coupon->restaurant_id && $coupon->restaurant_id != $cart->restaurant_id;

                if ($expired || $wrongRestaurant) {
                    // Auto-remove invalid coupon code from cart
                    $cart->update(['coupon_code' => null]);
                } else {
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

        $taxRate = 0.10; // 10% tax
        $deliveryCharge = $subtotal > 0 ? 5.00 : 0.00; // Flat 5.00 delivery charge

        $tax = ($subtotal - $discount) * $taxRate;
        if ($tax < 0) {
            $tax = 0.00;
        }

        $total = ($subtotal - $discount) + $deliveryCharge + $tax;

        return [
            'cart' => [
                'id' => $cart->id,
                'restaurant_id' => $cart->restaurant_id,
                'restaurant_name' => $cart->restaurant->name,
                'coupon_code' => $cart->coupon_code,
            ],
            'items' => $itemsData,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'delivery_charge' => round($deliveryCharge, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * View the current cart.
     */
    public function view(Request $request)
    {
        $cart = Cart::where('user_id', $request->user()->id)->first();
        return response()->json($this->getCartResponse($cart));
    }

    /**
     * Add a product to the cart.
     */
    public function add(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $productId = $request->product_id;
        $quantity = $request->get('quantity', 1);

        $product = Product::find($productId);

        if ($product->restaurant->status !== 'active' || $product->status !== 'active') {
            return response()->json(['message' => 'Product is not available'], 404);
        }

        $userId = $request->user()->id;

        $cart = Cart::where('user_id', $userId)->first();
        if (!$cart) {
            $cart = Cart::create([
                'user_id' => $userId,
                'restaurant_id' => $product->restaurant_id,
            ]);
        } elseif ($cart->restaurant_id != $product->restaurant_id) {
            // Transition cart to new restaurant - clear items and update restaurant_id
            $cart->items()->delete();
            $cart->update([
                'restaurant_id' => $product->restaurant_id,
                'coupon_code' => null,
            ]);
        }

        // Calculate price after product discount
        $price = $product->price - $product->discount;
        if ($price < 0) {
            $price = 0.00;
        }

        $cartItem = CartItem::where('cart_id', $cart->id)
                            ->where('product_id', $productId)
                            ->first();

        if ($cartItem) {
            $cartItem->quantity += $quantity;
            $cartItem->save();
        } else {
            CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $productId,
                'quantity' => $quantity,
                'price' => $price,
            ]);
        }

        return response()->json([
            'message' => 'Product added to cart successfully',
            'cart' => $this->getCartResponse($cart),
        ]);
    }

    /**
     * Update cart item quantity.
     */
    public function updateQuantity(Request $request, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $cartItem = CartItem::whereHas('cart', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        $cartItem->update([
            'quantity' => $request->quantity,
        ]);

        $cart = Cart::find($cartItem->cart_id);

        return response()->json([
            'message' => 'Cart item quantity updated successfully',
            'cart' => $this->getCartResponse($cart),
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function deleteItem(Request $request, $itemId)
    {
        $userId = $request->user()->id;
        $cartItem = CartItem::whereHas('cart', function($q) use ($userId) {
            $q->where('user_id', $userId);
        })->find($itemId);

        if (!$cartItem) {
            return response()->json(['message' => 'Cart item not found'], 404);
        }

        $cartId = $cartItem->cart_id;
        $cartItem->delete();

        $cart = Cart::find($cartId);

        if ($cart && $cart->items()->count() === 0) {
            $cart->delete();
            $cart = null;
        }

        return response()->json([
            'message' => 'Cart item removed successfully',
            'cart' => $this->getCartResponse($cart),
        ]);
    }

    /**
     * Apply a coupon code.
     */
    public function applyCoupon(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userId = $request->user()->id;
        $cart = Cart::where('user_id', $userId)->first();

        if (!$cart || $cart->items()->count() === 0) {
            return response()->json(['message' => 'Cart is empty'], 400);
        }

        $coupon = Coupon::where('code', $request->code)->first();

        if (!$coupon) {
            return response()->json(['message' => 'Invalid coupon code'], 422);
        }

        $today = now()->startOfDay();
        if ($coupon->expiry_date && $coupon->expiry_date->lt($today)) {
            return response()->json(['message' => 'Coupon has expired'], 422);
        }

        if ($coupon->restaurant_id && $coupon->restaurant_id != $cart->restaurant_id) {
            return response()->json(['message' => 'Coupon is not valid for this restaurant'], 422);
        }

        $cart->update([
            'coupon_code' => $coupon->code,
        ]);

        return response()->json([
            'message' => 'Coupon applied successfully',
            'cart' => $this->getCartResponse($cart),
        ]);
    }

    /**
     * Remove the applied coupon code.
     */
    public function removeCoupon(Request $request)
    {
        $userId = $request->user()->id;
        $cart = Cart::where('user_id', $userId)->first();

        if ($cart) {
            $cart->update([
                'coupon_code' => null,
            ]);
        }

        return response()->json([
            'message' => 'Coupon removed successfully',
            'cart' => $this->getCartResponse($cart),
        ]);
    }

    /**
     * Clear all items and remove the cart.
     */
    public function clear(Request $request)
    {
        $userId = $request->user()->id;
        $cart = Cart::where('user_id', $userId)->first();

        if ($cart) {
            $cart->items()->delete();
            $cart->delete();
        }

        return response()->json([
            'message' => 'Cart cleared successfully',
            'cart' => $this->getCartResponse(null),
        ]);
    }
}
