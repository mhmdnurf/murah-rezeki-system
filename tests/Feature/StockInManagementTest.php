<?php

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function stockInOwner(): User
{
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    return $owner;
}

test('only owners can access stock in management', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)->get(route('owner.stock-in'))->assertForbidden();
    $this->actingAs(stockInOwner())->get(route('owner.stock-in'))->assertSuccessful();
});

test('owner can record stock in and create an inventory movement', function () {
    $owner = stockInOwner();
    $this->actingAs($owner);
    $category = Category::create(['name' => 'Makanan']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-001',
        'name' => 'Indomie Goreng',
        'selling_price' => 3500,
        'stock' => 12,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.stock-in')
        ->set('productId', (string) $product->id)
        ->set('quantity', '8')
        ->set('note', 'Restock supplier')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();
    $movement = InventoryMovement::query()->firstOrFail();

    expect($product->stock)->toBe(20)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_IN)
        ->and($movement->quantity)->toBe(8)
        ->and($movement->note)->toBe('Restock supplier')
        ->and($movement->creator->is($owner))->toBeTrue();
});

test('stock in requires a positive quantity', function () {
    $this->actingAs(stockInOwner());
    $category = Category::create(['name' => 'Minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-002',
        'name' => 'Aqua 600 ml',
        'selling_price' => 4000,
        'stock' => 5,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.stock-in')
        ->set('productId', (string) $product->id)
        ->set('quantity', '0')
        ->call('save')
        ->assertHasErrors(['quantity']);
});
