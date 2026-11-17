<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * List categories of a restaurant with optional search query and pagination.
     */
    public function index(Request $request, $restaurantId)
    {
        $restaurant = Restaurant::find($restaurantId);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        $query = Category::where('restaurant_id', $restaurantId);

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $categories = $query->paginate($perPage);

        return response()->json($categories);
    }

    /**
     * Create a new category for a restaurant.
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
            'name' => 'required|string|max:255',
            'image' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['restaurant_id'] = $restaurantId;
        $data['status'] = $data['status'] ?? 'active';

        $category = Category::create($data);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    /**
     * Edit/update a category.
     */
    public function update(Request $request, $id)
    {
        $category = Category::with('restaurant')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Authorize owner of the restaurant that owns the category
        if ($category->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this category'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'image' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $category->update(array_filter($validator->validated(), function ($value) {
            return $value !== null;
        }));

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ]);
    }

    /**
     * Delete a category.
     */
    public function destroy(Request $request, $id)
    {
        $category = Category::with('restaurant')->find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        // Authorize owner of the restaurant that owns the category
        if ($category->restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this category'], 403);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
