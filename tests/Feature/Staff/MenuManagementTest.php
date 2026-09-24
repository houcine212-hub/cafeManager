<?php

namespace Tests\Feature\Staff;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $manager;
    protected User $serveur;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->manager = User::where('email', 'manager@demo.test')->firstOrFail();
        $this->serveur = User::where('email', 'serveur@demo.test')->firstOrFail();

        app()->instance('current_cafe_id', $this->manager->cafe_id);
    }

    public function test_manager_can_create_and_update_menu_items(): void
    {
        $this->actingAs($this->manager)
            ->get(route('staff.menu'))
            ->assertOk()
            ->assertSee('Gérez le menu et le stock');

        $this->actingAs($this->manager)
            ->post(route('staff.menu.categories.store'), [
                'name' => 'Phase 8 Drinks',
                'sort_order' => 5,
            ])
            ->assertRedirect(route('staff.menu'));

        $category = Category::where('name', 'Phase 8 Drinks')->firstOrFail();

        $this->actingAs($this->manager)
            ->post(route('staff.menu.products.store'), [
                'category_id' => $category->id,
                'name' => 'Phase 8 Lemonade',
                'description' => 'Fresh lemonade',
                'price' => '18.00',
                'track_stock' => '1',
                'stock_quantity' => 5,
            ])
            ->assertRedirect(route('staff.menu'));

        $product = Product::where('name', 'Phase 8 Lemonade')->firstOrFail();

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovement::REASON_INITIAL,
            'delta' => 5,
            'quantity_after' => 5,
        ]);

        $this->actingAs($this->manager)
            ->patch(route('staff.menu.products.update', $product->id), [
                'category_id' => $category->id,
                'name' => 'Phase 8 Lemonade XL',
                'description' => 'Updated lemonade',
                'price' => '20.00',
                'is_available' => '1',
                'is_active' => '1',
                'track_stock' => '1',
                'stock_quantity' => 3,
            ])
            ->assertRedirect(route('staff.menu'));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Phase 8 Lemonade XL',
            'price' => '20.00',
            'stock_quantity' => 3,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovement::REASON_CORRECTION,
            'delta' => -2,
            'quantity_after' => 3,
        ]);
    }

    public function test_manager_can_hide_a_category_without_deleting_it(): void
    {
        $category = Category::where('name', 'Boissons Chaudes')->firstOrFail();

        $this->actingAs($this->manager)
            ->patch(route('staff.menu.categories.update', $category->id), [
                'name' => $category->name,
                'sort_order' => $category->sort_order,
                'is_active' => false,
            ])
            ->assertRedirect(route('staff.menu'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
    }

    public function test_serveur_cannot_manage_the_menu(): void
    {
        $this->actingAs($this->serveur)
            ->get(route('staff.menu'))
            ->assertForbidden();

        $this->actingAs($this->serveur)
            ->post(route('staff.menu.categories.store'), [
                'name' => 'Unauthorized Category',
            ])
            ->assertForbidden();
    }
}
