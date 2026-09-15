<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إنشاء المقهى التجريبي
        $cafeId = DB::table('cafes')->insertGetId([
            'name' => 'Café Atlas — DEMO',
            'address' => 'Boulevard Zerktouni, Maarif',
            'city' => 'Casablanca',
            'phone' => '0522001122',
            'email' => 'contact@cafeatlas.test',
            'currency' => 'MAD',
            'timezone' => 'Africa/Casablanca',
            'status' => 'active',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 2. إنشاء الأدوار
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'description' => 'مدير المقهى - كامل الصلاحيات',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serveurRoleId = DB::table('roles')->insertGetId([
            'name' => 'serveur',
            'description' => 'سرباي - استقبال الزبائن وتسيير الطلبات',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. إنشاء حسابات الموظفين (Password: password)
        $managerId = DB::table('users')->insertGetId([
            'cafe_id' => $cafeId,
            'role_id' => $managerRoleId,
            'name' => 'Amine El Amrani',
            'email' => 'manager@demo.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $serveurId = DB::table('users')->insertGetId([
            'cafe_id' => $cafeId,
            'role_id' => $serveurRoleId,
            'name' => 'Karim Bennani',
            'email' => 'serveur@demo.test',
            'password' => Hash::make('password'),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. إنشاء 6 طاولات مع رموز QR نشطة
        $tables = [
            ['label' => 'Table 1', 'capacity' => 2],
            ['label' => 'Table 2', 'capacity' => 2],
            ['label' => 'Table 3', 'capacity' => 4],
            ['label' => 'Table 4', 'capacity' => 4],
            ['label' => 'Terrasse 1', 'capacity' => 4],
            ['label' => 'Terrasse 2', 'capacity' => 6],
        ];

        foreach ($tables as $t) {
            $tableId = DB::table('cafe_tables')->insertGetId([
                'cafe_id' => $cafeId,
                'label' => $t['label'],
                'capacity' => $t['capacity'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // رمز QR فريد بطول 64 حرفاً
            DB::table('qr_codes')->insert([
                'cafe_id' => $cafeId,
                'table_id' => $tableId,
                'token' => bin2hex(random_bytes(32)),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. إنشاء تصنيفات المينو
        $catHot = DB::table('categories')->insertGetId([
            'cafe_id' => $cafeId,
            'name' => 'Boissons Chaudes',
            'sort_order' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catCold = DB::table('categories')->insertGetId([
            'cafe_id' => $cafeId,
            'name' => 'Boissons Fraîches',
            'sort_order' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catSnacks = DB::table('categories')->insertGetId([
            'cafe_id' => $cafeId,
            'name' => 'Viennoiseries & Snacks',
            'sort_order' => 3,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. إنشاء المنتجات المحضرة (بلا تتبع ستوك - track_stock = false)
        $preparedProducts = [
            ['cat' => $catHot, 'name' => 'Café Noir (Espresso)', 'price' => 10.00],
            ['cat' => $catHot, 'name' => 'Café au Lait (Noss-Noss)', 'price' => 12.00],
            ['cat' => $catHot, 'name' => 'Thé à la Menthe (Berrad)', 'price' => 13.00],
        ];

        foreach ($preparedProducts as $p) {
            DB::table('products')->insert([
                'cafe_id' => $cafeId,
                'category_id' => $p['cat'],
                'name' => $p['name'],
                'price' => $p['price'],
                'track_stock' => false,
                'stock_quantity' => null,
                'is_available' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 7. إنشاء المنتجات المعلبة (بتتبع الستوك D15 - track_stock = true)
        $trackedProducts = [
            ['cat' => $catCold, 'name' => 'Coca-Cola (33cl)', 'price' => 12.00, 'stock' => 24],
            ['cat' => $catCold, 'name' => 'Schweppes Citron (33cl)', 'price' => 13.00, 'stock' => 18],
            ['cat' => $catCold, 'name' => 'Eau Minérale Sidi Ali (0.5L)', 'price' => 6.00, 'stock' => 48],
            ['cat' => $catCold, 'name' => 'Red Bull (25cl)', 'price' => 25.00, 'stock' => 12],
            ['cat' => $catSnacks, 'name' => 'Pain au Chocolat (Petit Pain)', 'price' => 6.00, 'stock' => 20],
            ['cat' => $catSnacks, 'name' => 'Croissant au Beurre', 'price' => 5.00, 'stock' => 15],
        ];

        foreach ($trackedProducts as $p) {
            $productId = DB::table('products')->insertGetId([
                'cafe_id' => $cafeId,
                'category_id' => $p['cat'],
                'name' => $p['name'],
                'price' => $p['price'],
                'track_stock' => true,
                'stock_quantity' => $p['stock'],
                'is_available' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // حركة الستوك الأولية فـ stock_movements (D15)
            DB::table('stock_movements')->insert([
                'cafe_id' => $cafeId,
                'product_id' => $productId,
                'delta' => $p['stock'],
                'quantity_after' => $p['stock'],
                'reason' => 'initial',
                'order_item_id' => null,
                'actor_type' => 'user',
                'actor_id' => $managerId,
                'note' => 'الكمية الافتتاحية للمقهى التجريبي',
                'occurred_at' => now(),
            ]);
        }
    }
}