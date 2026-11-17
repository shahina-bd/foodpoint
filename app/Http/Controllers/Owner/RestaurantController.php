<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RestaurantController extends Controller
{
    /**
     * Create a new restaurant.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:restaurants,slug',
            'logo' => 'nullable|string|max:255',
            'cover' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        
        // Auto-generate unique slug if not provided
        if (empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $count = 1;
            while (Restaurant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $count++;
            }
            $data['slug'] = $slug;
        }

        $data['owner_id'] = $request->user()->id;
        $data['status'] = 'active'; // default status

        $restaurant = Restaurant::create($data);

        return response()->json([
            'message' => 'Restaurant created successfully',
            'restaurant' => $restaurant
        ], 201);
    }

    /**
     * View restaurant profile.
     */
    public function show(Request $request, $id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        if ($restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this restaurant'], 403);
        }

        return response()->json([
            'restaurant' => $restaurant
        ]);
    }

    /**
     * Update restaurant details.
     */
    public function update(Request $request, $id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        if ($restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this restaurant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:restaurants,slug,' . $id,
            'logo' => 'nullable|string|max:255',
            'cover' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['name']) && empty($data['slug'])) {
            $baseSlug = Str::slug($data['name']);
            $slug = $baseSlug;
            $count = 1;
            while (Restaurant::where('slug', $slug)->where('id', '!=', $id)->exists()) {
                $slug = $baseSlug . '-' . $count++;
            }
            $data['slug'] = $slug;
        }

        $restaurant->update(array_filter($data, function ($value) {
            return $value !== null;
        }));

        return response()->json([
            'message' => 'Restaurant updated successfully',
            'restaurant' => $restaurant
        ]);
    }

    /**
     * Delete a restaurant.
     */
    public function destroy(Request $request, $id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        if ($restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this restaurant'], 403);
        }

        $restaurant->delete();

        return response()->json([
            'message' => 'Restaurant deleted successfully'
        ]);
    }

    /**
     * Update restaurant settings (like status).
     */
    public function updateSettings(Request $request, $id)
    {
        $restaurant = Restaurant::find($id);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        if ($restaurant->owner_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized access to this restaurant'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $restaurant->update($validator->validated());

        return response()->json([
            'message' => 'Restaurant settings updated successfully',
            'restaurant' => $restaurant
        ]);
    }
}
