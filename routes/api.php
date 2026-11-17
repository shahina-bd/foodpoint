<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Owner\RestaurantController;
use App\Http\Controllers\Owner\CategoryController;
use App\Http\Controllers\Owner\ProductController;
use App\Http\Controllers\Customer\RestaurantController as CustomerRestaurantController;
use App\Http\Controllers\Customer\ProductController as CustomerProductController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Owner\OrderController as OwnerOrderController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public restaurant routes
Route::get('/restaurants', [CustomerRestaurantController::class, 'index']);
Route::get('/restaurants/{restaurant}', [CustomerRestaurantController::class, 'show']);

// Public category list with search and pagination
Route::get('/restaurants/{restaurant}/categories', [CategoryController::class, 'index']);

// Public product routes
Route::get('/restaurants/{restaurant}/products', [CustomerProductController::class, 'index']);
Route::get('/products/{product}', [CustomerProductController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Cart routes
    Route::get('/cart', [CartController::class, 'view']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::put('/cart/items/{item}', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/items/{item}', [CartController::class, 'deleteItem']);
    Route::post('/cart/coupon', [CartController::class, 'applyCoupon']);
    Route::delete('/cart/coupon', [CartController::class, 'removeCoupon']);
    Route::post('/cart/clear', [CartController::class, 'clear']);

    // Checkout route
    Route::post('/checkout', [CheckoutController::class, 'checkout']);

    // Customer order routes
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel']);
});

Route::middleware(['auth:sanctum', 'owner'])->prefix('owner')->group(function () {
    Route::post('/restaurants', [RestaurantController::class, 'store']);
    Route::get('/restaurants/{restaurant}', [RestaurantController::class, 'show']);
    Route::put('/restaurants/{restaurant}', [RestaurantController::class, 'update']);
    Route::delete('/restaurants/{restaurant}', [RestaurantController::class, 'destroy']);
    Route::put('/restaurants/{restaurant}/settings', [RestaurantController::class, 'updateSettings']);

    // Owner category management routes
    Route::post('/restaurants/{restaurant}/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // Owner product management routes
    Route::post('/restaurants/{restaurant}/products', [ProductController::class, 'store']);
    Route::put('/products/{product}', [ProductController::class, 'update']);
    Route::delete('/products/{product}', [ProductController::class, 'destroy']);
    Route::put('/products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::put('/products/{product}/discount', [ProductController::class, 'updateDiscount']);

    // Owner order management routes
    Route::get('/orders', [OwnerOrderController::class, 'index']);
    Route::get('/orders/{order}', [OwnerOrderController::class, 'show']);
    Route::post('/orders/{order}/accept', [OwnerOrderController::class, 'accept']);
    Route::post('/orders/{order}/reject', [OwnerOrderController::class, 'reject']);
    Route::post('/orders/{order}/complete', [OwnerOrderController::class, 'complete']);
    Route::get('/orders/{order}/invoice', [OwnerOrderController::class, 'invoice']);

    // Owner Dashboard route
    Route::get('/dashboard', [OwnerDashboardController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/statistics', [AdminOrderController::class, 'statistics']);
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);

    // Admin Reports routes
    Route::get('/reports/sales', [AdminReportController::class, 'sales']);
    Route::get('/reports/restaurants', [AdminReportController::class, 'restaurantEarnings']);
    Route::get('/reports/commissions', [AdminReportController::class, 'commissions']);
    Route::get('/reports/customers', [AdminReportController::class, 'customers']);

    // Admin Users routes
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}/role', [UserController::class, 'updateRole']);
    Route::post('/users/{user}/toggle-status', [UserController::class, 'toggleStatus']);
});
