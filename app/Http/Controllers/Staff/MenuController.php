<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(Request $request): View
    {
        $this->assertManager($request);

        return view('staff.menu', [
            'categories' => Category::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->with('category')
                ->orderBy('name')
                ->get(),
            'role' => $request->user()->role?->name ?? 'staff',
        ]);
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

        return redirect()->route('staff.menu')
            ->with('status', 'Catégorie créée.');
    }

    public function updateCategory(Request $request, int $categoryId): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $category = Category::query()
            ->where('cafe_id', $cafeId)
            ->whereKey($categoryId)
            ->firstOrFail();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId))
                    ->ignore($category->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
        ]);

        $category->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('staff.menu')
            ->with('status', 'Catégorie mise à jour.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $validated = $this->validateProduct($request, $cafeId);
        $trackStock = (bool) ($validated['track_stock'] ?? false);
        $stockQuantity = $trackStock
            ? (int) ($validated['stock_quantity'] ?? 0)
            : null;

        DB::transaction(function () use ($validated, $cafeId, $trackStock, $stockQuantity, $request): void {
            $product = Product::create([
                'cafe_id' => $cafeId,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'is_available' => true,
                'is_active' => true,
                'track_stock' => $trackStock,
                'stock_quantity' => $stockQuantity,
            ]);

            if ($trackStock && $stockQuantity > 0) {
                StockMovement::create([
                    'cafe_id' => $cafeId,
                    'product_id' => $product->id,
                    'delta' => $stockQuantity,
                    'quantity_after' => $stockQuantity,
                    'reason' => StockMovement::REASON_INITIAL,
                    'actor_type' => StockMovement::ACTOR_USER,
                    'actor_id' => $request->user()->id,
                    'note' => 'Stock initial ajouté depuis le menu.',
                ]);
            }
        });

        return redirect()->route('staff.menu')
            ->with('status', 'Produit créé.');
    }

    public function updateProduct(Request $request, int $productId): RedirectResponse
    {
        $this->assertManager($request);
        $cafeId = (int) $request->user()->cafe_id;

        $product = Product::query()
            ->where('cafe_id', $cafeId)
            ->whereKey($productId)
            ->firstOrFail();

        $validated = $this->validateProduct($request, $cafeId, $product->id);
        $trackStock = (bool) ($validated['track_stock'] ?? false);
        $newQuantity = $trackStock
            ? (int) ($validated['stock_quantity'] ?? 0)
            : null;
        $oldQuantity = $product->track_stock
            ? (int) $product->stock_quantity
            : null;

        $isAvailable = (bool) ($validated['is_available'] ?? true);
        $isActive = (bool) ($validated['is_active'] ?? true);

        DB::transaction(function () use ($product, $validated, $trackStock, $newQuantity, $oldQuantity, $cafeId, $request, $isAvailable, $isActive): void {
            $product->update([
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'price' => $validated['price'],
                'is_available' => $isAvailable,
                'is_active' => $isActive,
                'track_stock' => $trackStock,
                'stock_quantity' => $newQuantity,
            ]);

            if ($trackStock && $oldQuantity !== $newQuantity) {
                $delta = $newQuantity - (int) ($oldQuantity ?? 0);

                if ($delta !== 0) {
                    StockMovement::create([
                        'cafe_id' => $cafeId,
                        'product_id' => $product->id,
                        'delta' => $delta,
                        'quantity_after' => $newQuantity,
                        'reason' => StockMovement::REASON_CORRECTION,
                        'actor_type' => StockMovement::ACTOR_USER,
                        'actor_id' => $request->user()->id,
                        'note' => 'Correction de stock depuis le menu.',
                    ]);
                }
            }
        });

        return redirect()->route('staff.menu')
            ->with('status', 'Produit mis à jour.');
    }

    private function validateProduct(Request $request, int $cafeId, ?int $productId = null): array
    {
        return $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId)),
            ],
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('products', 'name')
                    ->where(fn ($query) => $query->where('cafe_id', $cafeId))
                    ->ignore($productId),
            ],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_available' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function assertManager(Request $request): void
    {
        abort_unless($request->user()?->isManager(), 403);
    }
}
