<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class OrderManagementTest extends TestCase
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

    public function test_customer_can_manage_orders(): void
    {
        Sanctum::actingAs($this->customer1);

        $orderPending = Order::create([
            'user_id' => $this->customer1->id,
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

        $orderCompleted = Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-COMPLETED',
            'subtotal' => 10,
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 1,
            'total' => 16,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        // List all
        $response = $this->getJson('/api/orders');
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');

        // Filter by pending
        $responseFiltered = $this->getJson('/api/orders?status=pending');
        $responseFiltered->assertStatus(200)
                         ->assertJsonCount(1, 'data')
                         ->assertJsonPath('data.0.order_number', 'ORD-PENDING');

        // Cancel order
        $responseCancel = $this->postJson("/api/orders/{$orderPending->id}/cancel");
        $responseCancel->assertStatus(200)
                       ->assertJsonPath('order.order_status', 'cancelled');

        // Try cancel completed order (should fail)
        $responseFail = $this->postJson("/api/orders/{$orderCompleted->id}/cancel");
        $responseFail->assertStatus(400);
    }

    public function test_owner_can_manage_orders(): void
    {
        Sanctum::actingAs($this->owner);

        $order = Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-OWNER-TEST',
            'subtotal' => 20,
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 2,
            'total' => 27,
            'order_status' => 'pending',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product1->id,
            'price' => 10,
            'quantity' => 2
        ]);

        Payment::create([
            'order_id' => $order->id,
            'method' => 'cash_on_delivery',
            'amount' => 27,
            'status' => 'pending'
        ]);

        // Owner List
        $response = $this->getJson('/api/owner/orders');
        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data');

        // Owner Accept
        $responseAccept = $this->postJson("/api/owner/orders/{$order->id}/accept");
        $responseAccept->assertStatus(200)
                       ->assertJsonPath('order.order_status', 'accepted');

        // Owner Complete
        $responseComplete = $this->postJson("/api/owner/orders/{$order->id}/complete");
        $responseComplete->assertStatus(200)
                         ->assertJsonPath('order.order_status', 'completed')
                         ->assertJsonPath('order.payment_status', 'completed');

        // Owner Invoice
        $responseInvoice = $this->getJson("/api/owner/orders/{$order->id}/invoice");
        $responseInvoice->assertStatus(200)
                        ->assertJsonPath('invoice.order_number', 'ORD-OWNER-TEST')
                        ->assertJsonPath('invoice.total', 27);
    }

    public function test_admin_can_view_orders_and_statistics(): void
    {
        // Place a completed order for restaurant 1
        $order1 = Order::create([
            'user_id' => $this->customer1->id,
            'restaurant_id' => $this->restaurant1->id,
            'order_number' => 'ORD-1',
            'subtotal' => 100, // 10.00 commission
            'discount' => 20, // net subtotal = 80, so 8.00 commission
            'delivery_charge' => 5,
            'tax' => 8,
            'total' => 93,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        // Place a completed order for restaurant 2
        $order2 = Order::create([
            'user_id' => $this->customer2->id,
            'restaurant_id' => $this->restaurant2->id,
            'order_number' => 'ORD-2',
            'subtotal' => 50, // net = 50, so 5.00 commission
            'discount' => 0,
            'delivery_charge' => 5,
            'tax' => 5,
            'total' => 60,
            'order_status' => 'completed',
            'address' => 'Delivery Addr',
            'phone' => '123'
        ]);

        Sanctum::actingAs($this->admin);

        // Admin List
        $response = $this->getJson('/api/admin/orders');
        $response->assertStatus(200)
                 ->assertJsonCount(2, 'data');

        // Admin Statistics
        $responseStats = $this->getJson('/api/admin/statistics');
        $responseStats->assertStatus(200)
                      ->assertJsonPath('statistics.total_orders', 2)
                      ->assertJsonPath('statistics.total_sales', 153) // 93 + 60 = 153
                      ->assertJsonPath('statistics.total_commission', 13) // 8.00 (from res1) + 5.00 (from res2) = 13
                      ->assertJsonCount(2, 'statistics.restaurant_breakdown')
                      ->assertJsonFragment([
                          'restaurant_name' => 'Pizza House',
                          'orders_count' => 1,
                          'total_sales' => 93,
                          'commission' => 8
                      ]);
    }
}
