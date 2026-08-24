<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function inventoryOwner(): User
{
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    return $owner;
}

test('only owners can access inventory management', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)->get(route('owner.inventory'))->assertForbidden();
    $this->actingAs(inventoryOwner())->get(route('owner.inventory'))->assertSuccessful();
});

test('owner can filter inventory by stock status', function () {
    $this->actingAs(inventoryOwner());
    $category = Category::create(['name' => 'Minuman']);

    Product::create(['category_id' => $category->id, 'sku' => 'BRG-001', 'name' => 'Aqua 600 ml', 'selling_price' => 4000, 'stock' => 18, 'is_active' => true]);
    Product::create(['category_id' => $category->id, 'sku' => 'BRG-002', 'name' => 'Susu Ultra Milk', 'selling_price' => 7000, 'stock' => 6, 'is_active' => true]);

    Livewire::test('pages::owner.inventory')
        ->set('statusFilter', 'low')
        ->assertSee('Susu Ultra Milk')
        ->assertDontSee('Aqua 600 ml');
});
