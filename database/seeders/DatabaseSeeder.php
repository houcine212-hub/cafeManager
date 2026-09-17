<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $demoPassword = env('DEMO_USER_PASSWORD');

        if (! $demoPassword && app()->environment(['local', 'testing'])) {
            $demoPassword = 'password';
        }

        if (! $demoPassword) {
            throw new RuntimeException(
                'Set DEMO_USER_PASSWORD before running the demo seeder outside local/testing.'
            );
        }

        DB::transaction(function () use ($demoPassword) {
            $now = now();

            $ensure = function (string $table, array $keys, array $values) use ($now): int {
                $query = DB::table($table);
                foreach ($keys as $column => $value) {
                    $query->where($column, $value);
                }

                $id = $query->value('id');

                if ($id) {
                    DB::table($table)->where('id', $id)->update(array_merge(
                        $values,
                        ['updated_at' => $now]
                    ));

                    return (int) $id;
                }

                return (int) DB::table($table)->insertGetId(array_merge(
                    $keys,
                    $values,
                    ['created_at' => $now, 'updated_at' => $now]
                ));
            };

            $ensureUser = function (array $keys, array $values) use ($now, $demoPassword): int {
                $query = DB::table('users');
                foreach ($keys as $column => $value) {
                    $query->where($column, $value);
                }

                $id = $query->value('id');

                if ($id) {
                    DB::table('users')->where('id', $id)->update(array_merge(
                        $values,
                        ['updated_at' => $now]
                    ));

                    return (int) $id;
                }

                return (int) DB::table('users')->insertGetId(array_merge(
                    $keys,
                    $values,
                    [
                        'password' => Hash::make($demoPassword),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                ));
            };

            $cafeId = $ensure('cafes',
                ['email' => 'contact@cafeatlas.test'],
                [
                    'name' => 'Café Atlas — DEMO',
                    'address' => 'Boulevard Zerktouni, Maarif',
                    'city' => 'Casablanca',
                    'phone' => '0522001122',
                    'currency' => 'MAD',
                    'timezone' => 'Africa/Casablanca',
                    'status' => 'active',
                    'is_active' => true,
                ]
            );

            $managerRoleId = $ensure('roles',
                ['name' => 'manager'],
                ['description' => 'مدير المقهى - كامل الصلاحيات']
            );

            $serveurRoleId = $ensure('roles',
                ['name' => 'serveur'],
                ['description' => 'سرباي - استقبال الزبائن وتسيير الطلبات']
            );

            $managerId = $ensureUser(
                ['cafe_id' => $cafeId, 'email' => 'manager@demo.test'],
                [
                    'role_id' => $managerRoleId,
                    'name' => 'Amine El Amrani',
                    'is_active' => true,
                ]
            );

            $ensureUser(
                ['cafe_id' => $cafeId, 'email' => 'serveur@demo.test'],
                [
                    'role_id' => $serveurRoleId,
                    'name' => 'Karim Bennani',
                    'is_active' => true,
                ]
            );

            $tables = [
                ['label' => 'Table 1', 'capacity' => 2],
                ['label' => 'Table 2', 'capacity' => 2],
                ['label' => 'Table 3', 'capacity' => 4],
                ['label' => 'Table 4', 'capacity' => 4],
                ['label' => 'Terrasse 1', 'capacity' => 4],
                ['label' => 'Terrasse 2', 'capacity' => 6],
            ];

            foreach ($tables as $table) {
                $tableId = $ensure('cafe_tables',
                    ['cafe_id' => $cafeId, 'label' => $table['label']],
                    [
                        'capacity' => $table['capacity'],
                        'is_active' => true,
                    ]
                );

                $hasQr = DB::table('qr_codes')
                    ->where('cafe_id', $cafeId)
                    ->where('table_id', $tableId)
                    ->exists();

                if (! $hasQr) {
                    DB::table('qr_codes')->insert([
                        'cafe_id' => $cafeId,
                        'table_id' => $tableId,
                        'token' => bin2hex(random_bytes(32)),
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            $catHot = $ensure('categories',
                ['cafe_id' => $cafeId, 'name' => 'Boissons Chaudes'],
                ['icon' => null, 'sort_order' => 1, 'is_active' => true]
            );

            $catCold = $ensure('categories',
                ['cafe_id' => $cafeId, 'name' => 'Boissons Fraîches'],
                ['icon' => null, 'sort_order' => 2, 'is_active' => true]
            );

            $catSnacks = $ensure('categories',
                ['cafe_id' => $cafeId, 'name' => 'Viennoiseries & Snacks'],
                ['icon' => null, 'sort_order' => 3, 'is_active' => true]
            );

            $ensureProduct = function (
                array $keys,
                array $values,
                bool $preserveExistingStock = false
            ) use ($ensure): array {
                $query = DB::table('products');
                foreach ($keys as $column => $value) {
                    $query->where($column, $value);
                }

                $id = $query->value('id');

                if ($id) {
                    if ($preserveExistingStock) {
                        unset($values['stock_quantity']);
                    }

                    DB::table('products')->where('id', $id)->update(array_merge(
                        $values,
                        ['updated_at' => now()]
                    ));

                    return [(int) $id, false];
                }

                return [$ensure('products', $keys, $values), true];
            };

            $preparedProducts = [
                ['category_id' => $catHot, 'name' => 'Café Noir (Espresso)', 'price' => 10.00],
                ['category_id' => $catHot, 'name' => 'Café au Lait (Noss-Noss)', 'price' => 12.00],
                ['category_id' => $catHot, 'name' => 'Thé à la Menthe (Berrad)', 'price' => 13.00],
            ];

            foreach ($preparedProducts as $product) {
                $ensureProduct(
                    ['cafe_id' => $cafeId, 'name' => $product['name']],
                    [
                        'category_id' => $product['category_id'],
                        'price' => $product['price'],
                        'track_stock' => false,
                        'stock_quantity' => null,
                        'is_available' => true,
                        'is_active' => true,
                    ]
                );
            }

            $trackedProducts = [
                ['category_id' => $catCold, 'name' => 'Coca-Cola (33cl)', 'price' => 12.00, 'stock' => 24],
                ['category_id' => $catCold, 'name' => 'Schweppes Citron (33cl)', 'price' => 13.00, 'stock' => 18],
                ['category_id' => $catCold, 'name' => 'Eau Minérale Sidi Ali (0.5L)', 'price' => 6.00, 'stock' => 48],
                ['category_id' => $catCold, 'name' => 'Red Bull (25cl)', 'price' => 25.00, 'stock' => 12],
                ['category_id' => $catSnacks, 'name' => 'Pain au Chocolat (Petit Pain)', 'price' => 6.00, 'stock' => 20],
                ['category_id' => $catSnacks, 'name' => 'Croissant au Beurre', 'price' => 5.00, 'stock' => 15],
            ];

            foreach ($trackedProducts as $product) {
                [$productId, $created] = $ensureProduct(
                    ['cafe_id' => $cafeId, 'name' => $product['name']],
                    [
                        'category_id' => $product['category_id'],
                        'price' => $product['price'],
                        'track_stock' => true,
                        'stock_quantity' => $product['stock'],
                        'is_available' => true,
                        'is_active' => true,
                    ],
                    true
                );

                if ($created) {
                    DB::table('stock_movements')->insert([
                        'cafe_id' => $cafeId,
                        'product_id' => $productId,
                        'delta' => $product['stock'],
                        'quantity_after' => $product['stock'],
                        'reason' => 'initial',
                        'order_item_id' => null,
                        'actor_type' => 'user',
                        'actor_id' => $managerId,
                        'note' => 'الكمية الافتتاحية للمقهى التجريبي',
                        'occurred_at' => $now,
                    ]);
                }
            }
        });
    }
}
