<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private $owner;
    private $restaurant;
    private $category;

    protected function setUp(): void
    {
        parent::setUp();

        // Create owner user
        $this->owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'phone' => '1234567890',
            'password' => bcrypt('password'),
            'role' => 'owner',
            'status' => 'active'
        ]);

        // Create owner role
        Role::create([
            'user_id' => $this->owner->id,
            'owner' => true,
            'admin' => false,
            'customer' => false
        ]);

        // Create restaurant
        $this->restaurant = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'status' => 'active'
        ]);

        // Create category
        $this->category = Category::create([
            'restaurant_id' => $this->restaurant->id,
            'name' => 'Pizzas',
            'status' => 'active'
        ]);
    }

    public function test_can_list_products_publicly(): void
    {
        Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $response = $this->getJson("/api/restaurants/{$this->restaurant->id}/products");

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Margherita Pizza']);
    }

    public function test_can_show_single_product_publicly(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('product.name', 'Margherita Pizza');
    }

    public function test_owner_can_create_product(): void
    {
        Sanctum::actingAs($this->owner);

        $response = $this->postJson("/api/owner/restaurants/{$this->restaurant->id}/products", [
            'category_id' => $this->category->id,
            'name' => 'Pepperoni Pizza',
            'price' => 14.99,
            'description' => 'Delicious pepperoni pizza',
            'status' => 'active',
            'stock' => 50,
            'discount' => 1.50
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('product.name', 'Pepperoni Pizza')
                 ->assertJsonPath('product.slug', 'pepperoni-pizza');

        $this->assertDatabaseHas('products', [
            'name' => 'Pepperoni Pizza',
            'price' => 14.99
        ]);
    }

    public function test_owner_can_update_product(): void
    {
        Sanctum::actingAs($this->owner);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $response = $this->putJson("/api/owner/products/{$product->id}", [
            'name' => 'Updated Margherita Pizza',
            'price' => 13.99
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('product.name', 'Updated Margherita Pizza');
    }

    public function test_owner_can_update_product_stock(): void
    {
        Sanctum::actingAs($this->owner);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active',
            'stock' => 10
        ]);

        $response = $this->putJson("/api/owner/products/{$product->id}/stock", [
            'stock' => 25
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('product.stock', 25);
    }

    public function test_owner_can_update_product_discount(): void
    {
        Sanctum::actingAs($this->owner);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active',
            'discount' => 0.00
        ]);

        $response = $this->putJson("/api/owner/products/{$product->id}/discount", [
            'discount' => 2.50
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('product.discount', 2.50);
    }

    public function test_owner_can_delete_product(): void
    {
        Sanctum::actingAs($this->owner);

        $product = Product::create([
            'restaurant_id' => $this->restaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $response = $this->deleteJson("/api/owner/products/{$product->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('message', 'Product deleted successfully');

        $this->assertDatabaseMissing('products', [
            'id' => $product->id
        ]);
    }
}
