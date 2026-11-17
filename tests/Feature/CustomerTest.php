<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Role;
use App\Models\Restaurant;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    private $owner;
    private $activeRestaurant;
    private $inactiveRestaurant;
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

        // Create active restaurant
        $this->activeRestaurant = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Pizza House',
            'slug' => 'pizza-house',
            'address' => '123 Pizza Street',
            'description' => 'Great pizzas',
            'status' => 'active'
        ]);

        // Create inactive restaurant
        $this->inactiveRestaurant = Restaurant::create([
            'owner_id' => $this->owner->id,
            'name' => 'Inactive Pizza Place',
            'slug' => 'inactive-pizza-place',
            'status' => 'inactive'
        ]);

        // Create category
        $this->category = Category::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'name' => 'Pizzas',
            'status' => 'active'
        ]);
    }

    public function test_customer_can_list_active_restaurants(): void
    {
        $response = $this->getJson('/api/restaurants');

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Pizza House'])
                 ->assertJsonMissing(['name' => 'Inactive Pizza Place']);
    }

    public function test_customer_can_search_restaurants(): void
    {
        $response = $this->getJson('/api/restaurants?search=Street');

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Pizza House']);

        $responseEmpty = $this->getJson('/api/restaurants?search=Sushi');
        $responseEmpty->assertStatus(200)
                      ->assertJsonCount(0, 'data');
    }

    public function test_customer_can_show_restaurant_details(): void
    {
        $response = $this->getJson("/api/restaurants/{$this->activeRestaurant->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('restaurant.name', 'Pizza House');
    }

    public function test_customer_cannot_view_inactive_restaurant(): void
    {
        $response = $this->getJson("/api/restaurants/{$this->inactiveRestaurant->id}");

        $response->assertStatus(404);
    }

    public function test_customer_can_list_active_products_for_active_restaurant(): void
    {
        $activeProduct = Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $inactiveProduct = Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Hidden Pizza',
            'price' => 14.99,
            'status' => 'inactive'
        ]);

        $response = $this->getJson("/api/restaurants/{$this->activeRestaurant->id}/products");

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Margherita Pizza'])
                 ->assertJsonMissing(['name' => 'Hidden Pizza']);
    }

    public function test_customer_cannot_list_products_for_inactive_restaurant(): void
    {
        $response = $this->getJson("/api/restaurants/{$this->inactiveRestaurant->id}/products");

        $response->assertStatus(404);
    }

    public function test_customer_can_filter_products_by_category_search_and_price(): void
    {
        $category2 = Category::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'name' => 'Drinks',
            'status' => 'active'
        ]);

        Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $category2->id,
            'name' => 'Cola',
            'price' => 2.50,
            'status' => 'active'
        ]);

        // Category Filter
        $response = $this->getJson("/api/restaurants/{$this->activeRestaurant->id}/products?category_id={$this->category->id}");
        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Margherita Pizza'])
                 ->assertJsonMissing(['name' => 'Cola']);

        // Search Filter
        $responseSearch = $this->getJson("/api/restaurants/{$this->activeRestaurant->id}/products?search=Cola");
        $responseSearch->assertStatus(200)
                       ->assertJsonFragment(['name' => 'Cola'])
                       ->assertJsonMissing(['name' => 'Margherita Pizza']);

        // Price Filter
        $responsePrice = $this->getJson("/api/restaurants/{$this->activeRestaurant->id}/products?min_price=10.00");
        $responsePrice->assertStatus(200)
                      ->assertJsonFragment(['name' => 'Margherita Pizza'])
                      ->assertJsonMissing(['name' => 'Cola']);
    }

    public function test_customer_can_show_product_details(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Margherita Pizza',
            'price' => 12.99,
            'status' => 'active'
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(200)
                 ->assertJsonPath('product.name', 'Margherita Pizza');
    }

    public function test_customer_cannot_view_inactive_product_details(): void
    {
        $product = Product::create([
            'restaurant_id' => $this->activeRestaurant->id,
            'category_id' => $this->category->id,
            'name' => 'Inactive Margherita Pizza',
            'price' => 12.99,
            'status' => 'inactive'
        ]);

        $response = $this->getJson("/api/products/{$product->id}");

        $response->assertStatus(404);
    }
}
