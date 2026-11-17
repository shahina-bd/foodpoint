<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Restaurant;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * List products of a restaurant with optional search query, category, price range, status, discount, and pagination.
     */
    public function index(Request $request, $restaurantId)
    {
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        $query = Product::where('restaurant_id', $restaurantId);

        // Search functionality (name, description)
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Filtering by category_id
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        // Filtering by price range
        if ($request->has('min_price') && is_numeric($request->min_price)) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price') && is_numeric($request->max_price)) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filtering by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filtering by discount
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
     * View a single product.
     */
    public function show($id)
    {
        $product = Product::with(['restaurant', 'category'])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json([
            'product' => $product
        ]);
    }

    /**
     * Create a new product for a restaurant.
     */
    public function store(Request $request, $restaurantId)
    {
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        // Authorize owner
        if ($restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this restaurant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // Verify category belongs to this restaurant
        $category = Category::find($data['category_id']);
        if ($category->restaurant_id != $restaurantId) {
            return response()->json(['errors' => ['category_id' => ['The selected category does not belong to this restaurant.']]], 422);
        }

        // Handles image uploads
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        } elseif ($request->has('image') && is_string($request->image)) {
            $data['image'] = $request->image;
        }

        $data['restaurant_id'] = $restaurantId;
        $data['status'] = $data['status'] ?? 'active';
        $data['discount'] = $data['discount'] ?? 0.00;
        $data['stock'] = $data['stock'] ?? 0;

        $product = Product::create($data);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    /**
     * Edit/update a product.
     */
    public function update(Request $request, $id)
    {
        $product = Product::with('restaurant')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Authorize owner
        if ($product->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this product'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'image' => 'nullable',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = array_filter($validator->validated(), function($value) {
            return $value !== null;
        });

        // Verify category belongs to this restaurant if updated
        if (isset($data['category_id'])) {
            $category = Category::find($data['category_id']);
            if ($category->restaurant_id != $product->restaurant_id) {
                return response()->json(['errors' => ['category_id' => ['The selected category does not belong to this restaurant.']]], 422);
            }
        }

        // Handles image uploads
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = $imagePath;
        } elseif ($request->has('image') && is_string($request->image)) {
            $data['image'] = $request->image;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Delete a product.
     */
    public function destroy(Request $request, $id)
    {
        $product = Product::with('restaurant')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Authorize owner
        if ($product->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this product'], 403);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully'
        ]);
    }

    /**
     * Update product stock.
     */
    public function updateStock(Request $request, $id)
    {
        $product = Product::with('restaurant')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Authorize owner
        if ($product->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this product'], 403);
        }

        $validator = Validator::make($request->all(), [
            'stock' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'message' => 'Product stock updated successfully',
            'product' => $product
        ]);
    }

    /**
     * Update product discount.
     */
    public function updateDiscount(Request $request, $id)
    {
        $product = Product::with('restaurant')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Authorize owner
        if ($product->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this product'], 403);
        }

        $validator = Validator::make($request->all(), [
            'discount' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product->update($validator->validated());

        return response()->json([
            'message' => 'Product discount updated successfully',
            'product' => $product
        ]);
    }
}
