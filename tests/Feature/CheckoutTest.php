<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use App\Models\Coupon;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $owner;
    private $restaurant;
    private $category;
    private $product;
    private $coupon;

    protected function setUp(): void
    {
        parent::setUp();

        // Create owner & customer
        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'phone' => '1111111111',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 'active'
        ]);

        $this->customer = User::create([
            'name' => 'Customer User',
            'email' => 'customer@example.com',
            'phone' => '2222222222',
            'password' => bcrypt('password'),
            'role' => 'customer',
            'status' => 'active'
        ]);

        Role::create(['user_id' => $this->owner->id, 'owner' => true]);
        Role::create(['user_id' => $this->customer->id, 'customer' => true]);

        // Create active restaurant
        $this->restaurant = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active'
        ]);

        // Category
        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Pizzas',
            'status' => 'active'
        ]);

        // Product
        $this->product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 10.00,
            'discount' => 2.00, // selling price is 8.00
            'stock' => 100,
            'status' => 'active'
        ]);

        // Coupon
        $this->coupon = Coupon::create([
            'restaurant_id' => $this->restaurant->id,
            'code' => 'DISCOUNT25',
            'discount_type' => 'percentage',
            'discount' => 25.00,
            'expiry_date' => now()->addDays(7)
        ]);
    }

    public function test_can_checkout_successfully(): void
    {
        Sanctum::actingAs($this->customer);

        // Add product to cart & apply coupon
        $this->postJson('/api/cart/add', [
            'product_id' => $this->product->id,
            'quantity' => 2
        ]);

        $this->postJson('/api/cart/coupon', [
            'code' => 'DISCOUNT25'
        ]);

        $response = $this->postJson('/api/checkout', [
            'address' => '123 Delivery Street',
            'phone' => '9999999999',
            'notes' => 'Leave at door',
            'payment_method' => 'cash_on_delivery'
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('message', 'Order placed successfully')
                 ->assertJsonPath('order.subtotal', 16)
                 ->assertJsonPath('order.discount', 4)
                 ->assertJsonPath('order.delivery_charge', 5)
                 ->assertJsonPath('order.tax', 1.2)
                 ->assertJsonPath('order.total', 18.2)
                 ->assertJsonPath('order.payment_status', 'pending')
                 ->assertJsonPath('order.order_status', 'pending')
                 ->assertJsonPath('order.address', '123 Delivery Street')
                 ->assertJsonPath('order.phone', '9999999999')
                 ->assertJsonPath('order.notes', 'Leave at door');

        // Verify Order is in database
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->customer->id,
            'total' => 18.20,
            'address' => '123 Delivery Street'
        ]);

        // Verify OrderItem is created
        $this->assertDatabaseHas('order_items', [
            'product_id' => $this->product->id,
            'quantity' => 2,
            'price' => 8.00
        ]);

        // Verify Payment is registered
        $this->assertDatabaseHas('payments', [
            'amount' => 18.20,
            'method' => 'cash_on_delivery',
            'status' => 'pending'
        ]);

        // Verify stock is decremented
        $this->assertEquals(98, $this->product->fresh()->stock);

        // Verify Cart is cleared
        $this->assertDatabaseMissing('carts', [
            'user_id' => $this->customer->id
        ]);
    }

    public function test_checkout_fails_with_empty_cart(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/checkout', [
            'address' => '123 Delivery Street',
            'phone' => '9999999999',
            'payment_method' => 'cash_on_delivery'
        ]);

        $response->assertStatus(400)
                 ->assertJsonPath('message', 'Cart is empty');
    }

    public function test_checkout_fails_validation_if_missing_fields(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product->id,
            'quantity' => 1
        ]);

        $response = $this->postJson('/api/checkout', [
            // missing address and phone
            'payment_method' => 'cash_on_delivery'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['address', 'phone']);
    }
}
