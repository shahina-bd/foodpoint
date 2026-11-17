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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class CartTest extends TestCase
{
    use RefreshDatabase;

    private $customer;
    private $owner;
    private $restaurant1;
    private $restaurant2;
    private $category1;
    private $category2;
    private $product1;
    private $product2;

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

        // Create active restaurants
        $this->restaurant1 = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active'
        ]);

        $this->restaurant2 = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Burger House',
            'slug' => 'burger-house',
            'status' => 'active'
        ]);

        // Categories
        $this->category1 = Category::create([
            'restaurant_id' => $this->restaurant1->id,
            'name' => 'Pizzas',
            'status' => 'active'
        ]);

        $this->category2 = Category::create([
            'restaurant_id' => $this->restaurant2->id,
            'name' => 'Burgers',
            'status' => 'active'
        ]);

        // Products
        $this->product1 = Product::create([
            'restaurant_id' => $this->restaurant1->id,
            'category_id' => $this->category1->id,
            'name' => 'Margherita Pizza',
            'price' => 10.00,
            'discount' => 2.00, // selling price is 8.00
            'status' => 'active'
        ]);

        $this->product2 = Product::create([
            'restaurant_id' => $this->restaurant2->id,
            'category_id' => $this->category2->id,
            'name' => 'Cheese Burger',
            'price' => 8.00,
            'discount' => 0.00, // selling price is 8.00
            'status' => 'active'
        ]);
    }

    public function test_can_add_item_to_cart(): void
    {
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('cart.subtotal', 16) // 2 * (10.00 - 2.00)
                 ->assertJsonPath('cart.delivery_charge', 5)
                 ->assertJsonPath('cart.tax', 1.6) // 10% of 16.00
                 ->assertJsonPath('cart.total', 22.6); // 16.00 + 5.00 + 1.60
    }

    public function test_can_update_cart_item_quantity(): void
    {
        Sanctum::actingAs($this->customer);

        // Add item
        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1
        ]);

        $cart = Cart::where('user_id', $this->customer->id)->first();
        $item = CartItem::where('cart_id', $cart->id)->first();

        $response = $this->putJson("/api/cart/items/{$item->id}", [
            'quantity' => 3
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('cart.subtotal', 24); // 3 * 8.00
    }

    public function test_can_remove_item_from_cart(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1
        ]);

        $cart = Cart::where('user_id', $this->customer->id)->first();
        $item = CartItem::where('cart_id', $cart->id)->first();

        $response = $this->deleteJson("/api/cart/items/{$item->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('cart.cart', null);
    }

    public function test_adding_product_from_different_restaurant_clears_cart(): void
    {
        Sanctum::actingAs($this->customer);

        // Add from restaurant 1
        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1
        ]);

        // Add from restaurant 2
        $response = $this->postJson('/api/cart/add', [
            'product_id' => $this->product2->id,
            'quantity' => 1
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('cart.cart.restaurant_id', $this->restaurant2->id)
                 ->assertJsonCount(1, 'cart.items')
                 ->assertJsonPath('cart.subtotal', 8); // Margherita Pizza (8.00) is cleared, only Cheese Burger (8.00) is present
    }

    public function test_can_apply_percentage_coupon(): void
    {
        Sanctum::actingAs($this->customer);

        $coupon = Coupon::create([
            'restaurant_id' => $this->restaurant1->id,
            'code' => 'DISCOUNT25',
            'discount_type' => 'percentage',
            'discount' => 25.00, // 25%
            'expiry_date' => now()->addDays(7)
        ]);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2
        ]);

        $response = $this->postJson('/api/cart/coupon', [
            'code' => 'DISCOUNT25'
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('cart.subtotal', 16)
                 ->assertJsonPath('cart.discount', 4) // 25% of 16.00
                 ->assertJsonPath('cart.tax', 1.2) // 10% of (16.00 - 4.00) = 1.20
                 ->assertJsonPath('cart.total', 18.2); // 12.00 + 5.00 + 1.20
    }

    public function test_cannot_apply_expired_coupon(): void
    {
        Sanctum::actingAs($this->customer);

        $coupon = Coupon::create([
            'restaurant_id' => $this->restaurant1->id,
            'code' => 'EXPIRED',
            'discount_type' => 'fixed',
            'discount' => 5.00,
            'expiry_date' => now()->subDays(1)
        ]);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1
        ]);

        $response = $this->postJson('/api/cart/coupon', [
            'code' => 'EXPIRED'
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Coupon has expired');
    }

    public function test_cannot_apply_coupon_of_different_restaurant(): void
    {
        Sanctum::actingAs($this->customer);

        $coupon = Coupon::create([
            'restaurant_id' => $this->restaurant2->id,
            'code' => 'BURGERONLY',
            'discount_type' => 'fixed',
            'discount' => 2.00,
            'expiry_date' => now()->addDays(1)
        ]);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 1
        ]);

        $response = $this->postJson('/api/cart/coupon', [
            'code' => 'BURGERONLY'
        ]);

        $response->assertStatus(422)
                 ->assertJsonPath('message', 'Coupon is not valid for this restaurant');
    }

    public function test_can_remove_coupon(): void
    {
        Sanctum::actingAs($this->customer);

        $coupon = Coupon::create([
            'restaurant_id' => $this->restaurant1->id,
            'code' => 'DISCOUNT25',
            'discount_type' => 'percentage',
            'discount' => 25.00,
            'expiry_date' => now()->addDays(7)
        ]);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2
        ]);

        $this->postJson('/api/cart/coupon', [
            'code' => 'DISCOUNT25'
        ]);

        $response = $this->deleteJson('/api/cart/coupon');

        $response->assertStatus(200)
                 ->assertJsonPath('cart.discount', 0);
    }

    public function test_can_clear_cart(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/cart/add', [
            'product_id' => $this->product1->id,
            'quantity' => 2
        ]);

        $response = $this->postJson('/api/cart/clear');

        $response->assertStatus(200)
                 ->assertJsonPath('cart.cart', null);
    }
}
