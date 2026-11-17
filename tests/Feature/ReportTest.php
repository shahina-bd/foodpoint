<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private $customer1;
    private $customer2;
    private $owner;
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
        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'phone' => '1111111111',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->customer1 = User::create([
            'name' => 'Customer User 1',
            'email' => 'customer1@example.com',
            'phone' => '2222222222',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active'
        ]);

        $this->customer2 = User::create([
            'name' => 'Customer User 2',
            'email' => 'customer2@example.com',
            'phone' => '3333333333',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active'
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'phone' => '4444444444',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'status' => 'active'
        ]);

        Role::create(['user_id' => $this->owner->id, 'owner' => true]);
        Role::create(['user_id' => $this->customer1->id, 'customer' => true]);
        Role::create(['user_id' => $this->customer2->id, 'customer' => true]);
        Role::create(['user_id' => $this->admin->id, 'admin' => true]);

        // Restaurants
        $this->restaurant1 = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active'
        ]);

        $this->restaurant2 = Restaurant::create([
            'owner_id' => $this->owner->id,
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

    public function test_admin_can_retrieve_sales_reports(): void
    {
        // Place completed orders
        Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 100, // commission = 10
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 10,
            'total' => 115,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->admin);

        // Daily Sales
        $responseDaily = $this->getJson('/api/admin/reports/sales?group_by=daily');
        $responseDaily->assertStatus(200)
                      ->assertJsonCount(1)
                      ->assertJsonPath('0.total_sales', 115);

        // Monthly Sales
        $responseMonthly = $this->getJson('/api/admin/reports/sales?group_by=monthly');
        $responseMonthly->assertStatus(200)
                        ->assertJsonCount(1)
                        ->assertJsonPath('0.total_sales', 115)
                        ->assertJsonPath('0.period', now()->format('Y-m'));

        // Yearly Sales
        $responseYearly = $this->getJson('/api/admin/reports/sales?group_by=yearly');
        $responseYearly->assertStatus(200)
                       ->assertJsonCount(1)
                       ->assertJsonPath('0.total_sales', 115)
                       ->assertJsonPath('0.period', now()->format('Y'));
    }

    public function test_admin_can_retrieve_restaurant_earnings_report(): void
    {
        Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 100, // commission = 10
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 10,
            'total' => 115,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/restaurants');
        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonPath('0.restaurant_name', 'Pizza House')
                 ->assertJsonPath('0.total_sales', 115)
                 ->assertJsonPath('0.commission', 10)
                 ->assertJsonPath('0.earnings', 105); // 115 - 10 = 105
    }

    public function test_admin_can_retrieve_commissions_report(): void
    {
        Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 100, // commission = 10
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 10,
            'total' => 115,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->admin);

        $response = $this->getJson('/api/admin/reports/commissions');
        $response->assertStatus(200)
                 ->assertJsonCount(1)
                 ->assertJsonPath('0.commission', 10)
                 ->assertJsonPath('0.orders_count', 1);
    }

    public function test_admin_can_retrieve_customers_report(): void
    {
        Order::create([
            'user_id' => $this->customer1->id,
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

        $response = $this->getJson('/api/admin/reports/customers');
        $response->assertStatus(200)
                 ->assertJsonCount(2) // customer1 and customer2
                 ->assertJsonPath('0.email', 'customer1@example.com')
                 ->assertJsonPath('0.orders_count', 1)
                 ->assertJsonPath('0.total_spent', 115)
                 ->assertJsonPath('0.average_order_value', 115)
                 ->assertJsonPath('1.email', 'customer2@example.com')
                 ->assertJsonPath('1.orders_count', 0)
                 ->assertJsonPath('1.total_spent', 0)
                 ->assertJsonPath('1.average_order_value', 0);
    }
}
