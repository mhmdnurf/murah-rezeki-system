<?php

namespace Database\Seeders;

use App\Models\Category;
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

        $this->seedUser(
            username: 'pemilik',
            name: 'Pemilik',
            email: 'pemilik@example.com',
            password: 'password',
            role: $ownerRole,
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
}
