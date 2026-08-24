<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $ownerRole = Role::updateOrCreate(
            ['slug' => Role::OWNER],
            ['name' => 'Owner'],
        );

        Role::updateOrCreate(
            ['slug' => Role::CASHIER],
            ['name' => 'Cashier'],
        );

        foreach (['Makanan', 'Minuman', 'Perawatan', 'Kebutuhan Rumah Tangga'] as $categoryName) {
            Category::updateOrCreate(['name' => $categoryName]);
        }

        $this->seedProducts();

        $this->seedUser(
            username: 'pemilik',
            name: 'Pemilik',
            email: 'pemilik@example.com',
            password: 'password',
            role: $ownerRole,
        );

        $this->seedInventoryMovements(
            User::query()->where('username', 'pemilik')->firstOrFail(),
        );

        $this->seedUser(
            username: 'kasir',
            name: 'Kasir',
            email: 'kasir@example.com',
            password: 'password',
            role: Role::where('slug', Role::CASHIER)->firstOrFail(),
        );
    }

    private function seedUser(string $username, string $name, string $email, string $password, Role $role): void
    {
        $user = User::query()->firstOrNew(['username' => $username]);
        $user->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'role_id' => $role->id,
            'email_verified_at' => now(),
        ]);
        $user->save();
    }

    private function seedProducts(): void
    {
        $categoryIds = Category::query()->pluck('id', 'name');

        $products = [
            ['sku' => 'BRG-001', 'name' => 'Indomie Goreng', 'category' => 'Makanan', 'selling_price' => 3500, 'stock' => 240],
            ['sku' => 'BRG-002', 'name' => 'Aqua 600 ml', 'category' => 'Minuman', 'selling_price' => 4000, 'stock' => 18],
            ['sku' => 'BRG-003', 'name' => 'Teh Botol Sosro', 'category' => 'Minuman', 'selling_price' => 5000, 'stock' => 96],
            ['sku' => 'BRG-004', 'name' => 'Gula Pasir 1 kg', 'category' => 'Kebutuhan Rumah Tangga', 'selling_price' => 16500, 'stock' => 8],
            ['sku' => 'BRG-005', 'name' => 'Minyak Goreng 1 Liter', 'category' => 'Kebutuhan Rumah Tangga', 'selling_price' => 18000, 'stock' => 54],
            ['sku' => 'BRG-006', 'name' => 'Rinso 800 gram', 'category' => 'Perawatan', 'selling_price' => 24000, 'stock' => 0],
            ['sku' => 'BRG-007', 'name' => 'Lifebuoy Sabun Mandi', 'category' => 'Perawatan', 'selling_price' => 5500, 'stock' => 132],
            ['sku' => 'BRG-008', 'name' => 'Kopi Kapal Api', 'category' => 'Minuman', 'selling_price' => 2500, 'stock' => 310],
            ['sku' => 'BRG-009', 'name' => 'Susu Ultra Milk', 'category' => 'Minuman', 'selling_price' => 7000, 'stock' => 6],
            ['sku' => 'BRG-010', 'name' => 'Beras 5 kg', 'category' => 'Kebutuhan Rumah Tangga', 'selling_price' => 72000, 'stock' => 0],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['sku' => $product['sku']],
                [
                    'category_id' => $categoryIds[$product['category']],
                    'name' => $product['name'],
                    'selling_price' => $product['selling_price'],
                    'stock' => $product['stock'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedInventoryMovements(User $owner): void
    {
        foreach (Product::query()->get() as $product) {
            $receivedQuantity = max($product->stock + 5, 10);
            $issuedQuantity = $receivedQuantity - $product->stock;

            InventoryMovement::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'type' => InventoryMovement::TYPE_IN,
                    'note' => 'Stok awal seed',
                ],
                [
                    'quantity' => $receivedQuantity,
                    'created_by' => $owner->id,
                    'created_at' => now()->subDays(3),
                    'updated_at' => now()->subDays(3),
                ],
            );

            InventoryMovement::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'note' => 'Pengeluaran simulasi seed',
                ],
                [
                    'quantity' => $issuedQuantity,
                    'created_by' => $owner->id,
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ],
            );
        }
    }
}
