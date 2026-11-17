<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $owner1;
    private $owner2;
    private $admin;
    private $restaurant1;
    private $restaurant2;
    private $category;
    private $product1;
    private $product2;

    protected function setUp(): void
    {
        parent::setUp();

        // Create users
        $this->owner1 = User::create([
            'name' => 'Owner 1',
            'email' => 'owner1@example.com',
            'phone' => '1111111111',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->owner2 = User::create([
            'name' => 'Owner 2',
            'email' => 'owner2@example.com',
            'phone' => '2222222222',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->customer = User::create([
            'name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => '3333333333',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active'
        ]);

        $this->admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'phone' => '4444444444',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active'
        ]);

        Role::create(['user_id' => $this->owner1->id, 'owner' => true]);
        Role::create(['user_id' => $this->owner2->id, 'owner' => true]);
        Role::create(['user_id' => $this->customer->id, 'customer' => true]);
        Role::create(['user_id' => $this->admin->id, 'admin' => true]);

        // Restaurants
        $this->restaurant1 = Restaurant::create([
            'owner_id' => $this->owner1->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active'
        ]);

        $this->restaurant2 = Restaurant::create([
            'owner_id' => $this->owner2->id,
            'name' => 'Burger Place',
            'slug' => 'burger-place',
            'status' => 'active'
        ]);

        $this->category = Category::create([
            'restaurant_id' => $this->restaurant1->id,
            'name' => 'Food',
            'status' => 'active'
        ]);

        $this->product1 = Product::create([
            'restaurant_id' => $this->restaurant1->id,
            'category_id' => $this->category->id,
            'name' => 'Pizza',
            'price' => 10.00,
            'status' => 'active'
        ]);

        $this->product2 = Product::create([
            'restaurant_id' => $this->restaurant2->id,
            'category_id' => $this->category->id,
            'name' => 'Burger',
            'price' => 8.00,
            'status' => 'active'
        ]);
    }

    public function test_admin_dashboard_returns_analytics(): void
    {
        // Place completed orders
        $order = Order::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 100,
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 10,
            'total' => 115,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(200)
                 ->assertJsonPath('total_restaurants', 2)
                 ->assertJsonPath('customers', 1) // customer user
                 ->assertJsonPath('revenue', 115)
                 ->assertJsonPath('orders_today', 1)
                 ->assertJsonCount(1, 'sales_chart')
                 ->assertJsonCount(1, 'top_restaurants')
                 ->assertJsonPath('top_restaurants.0.name', 'Pizza House')
                 ->assertJsonPath('top_restaurants.0.total_sales', 115);
    }

    public function test_owner_dashboard_returns_analytics(): void
    {
        // Completed order today
        $orderCompleted = Order::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 20,
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 2,
            'total' => 27,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        OrderItem::create([
            'order_id' => $orderCompleted->id,
            'product_id' => $this->product1->id,
            'price' => 10,
            'quantity' => 2
        ]);

        // Pending order
        Order::create([
            'user_id' => $this->customer->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-PENDING',
            'subtotal' => 10,
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 1,
            'total' => 16,
            'order_status' => 'pending',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->owner1);

        // View owner dashboard
        $response = $this->getJson('/api/owner/dashboard');

        $response->assertStatus(200)
                 ->assertJsonPath('today_sales', 27)
                 ->assertJsonPath('weekly_sales', 27)
                 ->assertJsonPath('monthly_sales', 27)
                 ->assertJsonCount(1, 'popular_foods')
                 ->assertJsonPath('popular_foods.0.name', 'Pizza')
                 ->assertJsonPath('popular_foods.0.total_quantity', 2)
                 ->assertJsonPath('pending_orders.count', 1)
                 ->assertJsonCount(1, 'pending_orders.list');

        // Test filtering by restaurant_id (restaurant 1 owned by owner1)
        $responseFilter = $this->getJson("/api/owner/dashboard?restaurant_id={$this->restaurant1->id}");
        $responseFilter->assertStatus(200)
                       ->assertJsonPath('today_sales', 27);

        // Test filtering by restaurant_id they do not own (restaurant 2 owned by owner2) -> should return 403
        $responseUnauthorized = $this->getJson("/api/owner/dashboard?restaurant_id={$this->restaurant2->id}");
        $responseUnauthorized->assertStatus(403);
    }
}
