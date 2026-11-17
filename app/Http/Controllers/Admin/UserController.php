<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Get all users.
     */
    public function index()
    {
        $users = User::with('role')->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role, // Returns the Role relation object
                'status' => $user->status,
                'joinedDate' => $user->created_at->toDateString(),
            ];
        });

        return response()->json($users);
    }

    /**
     * Create a new user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string|in:admin,owner,customer,rider,driver,staff',
            'phone' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        
        // Map driver role to rider in backend
        $roleName = $data['role'] === 'driver' ? 'rider' : $data['role'];

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $roleName,
            'status' => 'active',
        ]);

        Role::create([
            'user_id' => $user->id,
            'admin' => $roleName === 'admin',
            'owner' => $roleName === 'owner',
            'customer' => $roleName === 'customer',
            'rider' => $roleName === 'rider',
            'staff' => $roleName === 'staff',
        ]);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->load('role')->role,
            'status' => $user->status,
            'joinedDate' => $user->created_at->toDateString(),
        ], 201);
    }

    /**
     * Update user role.
     */
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|string|in:admin,owner,customer,rider,driver,staff',
        ]);

        $roleName = $request->role === 'driver' ? 'rider' : $request->role;

        $user->update(['role' => $roleName]);

        Role::updateOrCreate(
            ['user_id' => $user->id],
            [
                'admin' => $roleName === 'admin',
                'owner' => $roleName === 'owner',
                'customer' => $roleName === 'customer',
                'rider' => $roleName === 'rider',
                'staff' => $roleName === 'staff',
            ]
        );

        return response()->json([
            'success' => true,
            'role' => $user->load('role')->role
        ]);
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(User $user)
    {
        $newStatus = strtolower($user->status) === 'active' ? 'suspended' : 'active';
        $user->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'status' => ucfirst($newStatus)
        ]);
    }
}
