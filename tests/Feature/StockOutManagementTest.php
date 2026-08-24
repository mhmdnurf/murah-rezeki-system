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

function stockOutOwner(): User
{
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    return $owner;
}

test('only owners can access stock out management', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)->get(route('owner.stock-out'))->assertForbidden();
    $this->actingAs(stockOutOwner())->get(route('owner.stock-out'))->assertSuccessful();
});

test('owner can record stock out and decrease product stock', function () {
    $owner = stockOutOwner();
    $this->actingAs($owner);
    $category = Category::create(['name' => 'Perawatan']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-001',
        'name' => 'Rinso 800 gram',
        'selling_price' => 24000,
        'stock' => 20,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.stock-out')
        ->set('productId', (string) $product->id)
        ->set('quantity', '7')
        ->set('note', 'Barang rusak')
        ->call('save')
        ->assertHasNoErrors();

    $product->refresh();
    $movement = InventoryMovement::query()->firstOrFail();

    expect($product->stock)->toBe(13)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_OUT)
        ->and($movement->quantity)->toBe(7)
        ->and($movement->note)->toBe('Barang rusak')
        ->and($movement->creator->is($owner))->toBeTrue();
});

test('stock out cannot exceed available stock', function () {
    $this->actingAs(stockOutOwner());
    $category = Category::create(['name' => 'Minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-002',
        'name' => 'Aqua 600 ml',
        'selling_price' => 4000,
        'stock' => 5,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.stock-out')
        ->set('productId', (string) $product->id)
        ->set('quantity', '6')
        ->call('save')
        ->assertHasErrors(['quantity']);

    expect($product->refresh()->stock)->toBe(5)
        ->and(InventoryMovement::query()->count())->toBe(0);
});
