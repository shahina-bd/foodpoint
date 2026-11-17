<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    /**
     * List all active restaurants with optional search query and pagination.
     */
    public function index(Request $request)
    {
        $query = Restaurant::where('status', 'active');

        // Search by name, description, address
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('address', 'like', '%' . $search . '%');
            });
        }

        // Pagination
        $perPage = $request->get('per_page', 10);
        $restaurants = $query->paginate($perPage);

        return response()->json($restaurants);
    }

    /**
     * Retrieve details of a single active restaurant.
     */
    public function show($id)
    {
        $restaurant = Restaurant::where('status', 'active')->find($id);

        if (!$restaurant) {
            return response()->json(['message' => 'Restaurant not found'], 404);
        }

        return response()->json([
            'restaurant' => $restaurant
        ]);
    }
}
