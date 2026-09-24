<?php

namespace App\Http\Controllers;

use App\Models\Cafe;
use App\Models\CafeTable;
use App\Models\Category;
use App\Models\Product;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function createRegistration(): View
    {
        return view('auth.register');
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cafe_name' => ['required', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:20'],
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:100',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $managerRole = Role::firstOrCreate(
                ['name' => 'manager'],
                ['description' => 'Propriétaire et gestionnaire du café']
            );

            $cafe = Cafe::create([
                'name' => $validated['cafe_name'],
                'city' => $validated['city'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
                'subscription_plan' => 'trial',
                'status' => 'active',
                'is_active' => true,
            ]);

            return User::create([
                'cafe_id' => $cafe->id,
                'role_id' => $managerRole->id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'is_active' => true,
            ]);
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('onboarding.setup')
            ->with('status', 'Votre café est créé. Configurez maintenant votre espace.');
    }

    public function setup(Request $request): View
    {
        $this->assertManager($request);

        $tables = CafeTable::query()
            ->with('activeQrCode')
            ->orderBy('label')
            ->get();

        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $products = Product::query()
            ->with('category')
            ->latest('id')
            ->get();

        return view('onboarding.setup', compact(
            'tables',
            'categories',
            'products'
        ));
    }

    public function storeTable(Request $request): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $validated = $request->validate([
            'label' => [
                'required',
                'string',
                'max:50',
                Rule::unique('cafe_tables', 'label')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId)),
            ],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $cafeId): void {
            $table = CafeTable::create([
                'cafe_id' => $cafeId,
                'label' => $validated['label'],
                'capacity' => $validated['capacity'] ?? null,
                'is_active' => true,
            ]);

            QrCode::create([
                'cafe_id' => $cafeId,
                'table_id' => $table->id,
                'token' => bin2hex(random_bytes(32)),
                'is_active' => true,
            ]);
        });

        return back()->with('status', 'Table et QR code créés.');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId)),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        Category::create([
            'cafe_id' => $cafeId,
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => true,
        ]);

        return back()->with('status', 'Catégorie créée.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $validated = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId)),
            ],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'track_stock' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $trackStock = (bool) ($validated['track_stock'] ?? false);

        Product::create([
            'cafe_id' => $cafeId,
            'category_id' => $validated['category_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'is_available' => true,
            'is_active' => true,
            'track_stock' => $trackStock,
            'stock_quantity' => $trackStock
                ? (int) ($validated['stock_quantity'] ?? 0)
                : null,
        ]);

        return back()->with('status', 'Produit créé.');
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()?->isManager(), 403);
    }
}
