<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List active products of a restaurant, supporting search, category filtering, price range, and discount filtering.
     */
    public function index(Request $request, $restaurantId)
    {
        $restaurant = Restaurant::where('status', 'active')->find($restaurantId);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        $query = Product::where('restaurant_id', $restaurantId)->where('status', 'active');

        // Search Food (name, description)
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Category Filter
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        // Price range filtering
        if ($request->has('min_price') && is_numeric($request->min_price)) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price') && is_numeric($request->max_price)) {
            $query->where('price', '<=', $request->max_price);
        }

        // Discount filtering
        if ($request->has('discount')) {
            $discountVal = $request->discount;
            if ($discountVal === '1' || $discountVal === 'true' || $discountVal === 'yes') {
                $query->where('discount', '>', 0);
            } elseif (is_numeric($discountVal)) {
                $query->where('discount', '=', $discountVal);
            }
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $products = $query->paginate($perPage);

        return response()->json($products);
    }

    /**
     * Retrieve details of a single active product.
     */
    public function show($id)
    {
        $product = Product::where('status', 'active')->with(['restaurant', 'category'])->find($id);

        if (!$product || $product->restaurant->status !== 'active') {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $product
        ]);
    }
}
