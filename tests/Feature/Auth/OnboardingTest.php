<?php

namespace Tests\Feature\Auth;

use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_owner_can_register_a_cafe_and_reaches_setup(): void
    {
        $response = $this->post(route('register.store'), [
            'cafe_name' => 'Café Horizon',
            'city' => 'Rabat',
            'phone' => '0537000000',
            'name' => 'Sara Manager',
            'email' => 'sara@horizon.test',
            'password' => 'strong-password',
            'password_confirmation' => 'strong-password',
        ]);

        $response->assertRedirect(route('onboarding.setup'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('cafes', [
            'name' => 'Café Horizon',
            'city' => 'Rabat',
            'subscription_plan' => 'trial',
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'sara@horizon.test',
            'name' => 'Sara Manager',
        ]);
    }

    public function test_manager_can_create_table_qr_category_and_product(): void
    {
        $this->seed(DatabaseSeeder::class);
        $manager = User::where('email', 'manager@demo.test')->firstOrFail();

        $this->actingAs($manager)
            ->get(route('onboarding.setup'))
            ->assertOk()
            ->assertSee('Configurez votre café');

        $this->actingAs($manager)
            ->post(route('onboarding.tables.store'), [
                'label' => 'Onboarding Terrasse',
                'capacity' => 4,
            ])
            ->assertRedirect(route('onboarding.setup'));

        $table = CafeTable::where('label', 'Onboarding Terrasse')->firstOrFail();
        $this->assertDatabaseHas('qr_codes', [
            'table_id' => $table->id,
            'is_active' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('onboarding.categories.store'), [
                'name' => 'Onboarding Snacks',
                'sort_order' => 9,
            ])
            ->assertRedirect(route('onboarding.setup'));

        $category = Category::where('name', 'Onboarding Snacks')->firstOrFail();

        $this->actingAs($manager)
            ->post(route('onboarding.products.store'), [
                'category_id' => $category->id,
                'name' => 'Onboarding Cookie',
                'description' => 'Test product',
                'price' => '12.50',
                'track_stock' => '1',
                'stock_quantity' => 8,
            ])
            ->assertRedirect(route('onboarding.setup'));

        $this->assertDatabaseHas('products', [
            'category_id' => $category->id,
            'name' => 'Onboarding Cookie',
            'price' => '12.50',
            'track_stock' => true,
            'stock_quantity' => 8,
        ]);
    }
}
