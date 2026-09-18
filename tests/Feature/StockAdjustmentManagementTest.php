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

function stockAdjustmentUser(string $role): User
{
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', $role)->firstOrFail())->save();

    return $user;
}

function adjustableProduct(int $stock = 10): Product
{
    $category = Category::create(['name' => 'Minuman']);

    return Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-ADJ-001',
        'name' => 'Air Mineral',
        'selling_price' => 5000,
        'stock' => $stock,
        'is_active' => true,
    ]);
}

test('only owners can access stock adjustment management', function () {
    $cashier = stockAdjustmentUser(Role::CASHIER);
    $owner = stockAdjustmentUser(Role::OWNER);

    $this->actingAs($cashier)->get(route('owner.stock-adjustments'))->assertForbidden();
    $this->actingAs($owner)->get(route('owner.stock-adjustments'))->assertSuccessful();
});

test('owner can increase stock through an audited adjustment', function () {
    $owner = stockAdjustmentUser(Role::OWNER);
    $product = adjustableProduct(10);

    $this->actingAs($owner);
    Livewire::test('pages::owner.stock-adjustments')
        ->set('productId', (string) $product->id)
        ->set('actualStock', '16')
        ->set('note', 'Hasil stok opname')
        ->call('save')
        ->assertHasNoErrors();

    $movement = InventoryMovement::query()->firstOrFail();

    expect($product->refresh()->stock)->toBe(16)
        ->and($movement->type)->toBe(InventoryMovement::TYPE_ADJUSTMENT)
        ->and($movement->quantity)->toBe(6)
        ->and($movement->stock_before)->toBe(10)
        ->and($movement->stock_after)->toBe(16)
        ->and($movement->note)->toBe('Hasil stok opname')
        ->and($movement->creator->is($owner))->toBeTrue();
});

test('owner can decrease stock through an audited adjustment', function () {
    $owner = stockAdjustmentUser(Role::OWNER);
    $product = adjustableProduct(10);

    $this->actingAs($owner);
    Livewire::test('pages::owner.stock-adjustments')
        ->set('productId', (string) $product->id)
        ->set('actualStock', '4')
        ->set('note', 'Selisih stok fisik')
        ->call('save')
        ->assertHasNoErrors();

    $movement = InventoryMovement::query()->firstOrFail();

    expect($product->refresh()->stock)->toBe(4)
        ->and($movement->quantity)->toBe(6)
        ->and($movement->stock_before)->toBe(10)
        ->and($movement->stock_after)->toBe(4);
});

test('adjustment rejects unchanged stock and keeps history clean', function () {
    $owner = stockAdjustmentUser(Role::OWNER);
    $product = adjustableProduct(10);

    $this->actingAs($owner);
    Livewire::test('pages::owner.stock-adjustments')
        ->set('productId', (string) $product->id)
        ->set('actualStock', '10')
        ->set('note', 'Hitung ulang stok')
        ->call('save')
        ->assertHasErrors(['actualStock']);

    expect($product->refresh()->stock)->toBe(10)
        ->and(InventoryMovement::query()->count())->toBe(0);
});

test('adjustment rejects stale stock after another process changes it', function () {
    $owner = stockAdjustmentUser(Role::OWNER);
    $product = adjustableProduct(10);

    $this->actingAs($owner);
    $component = Livewire::test('pages::owner.stock-adjustments')
        ->set('productId', (string) $product->id);

    $product->update(['stock' => 8]);

    $component->set('actualStock', '12')
        ->set('note', 'Hasil stok opname')
        ->call('save')
        ->assertHasErrors(['actualStock']);

    expect($product->refresh()->stock)->toBe(8)
        ->and(InventoryMovement::query()->count())->toBe(0);
});

test('adjustment requires non-negative stock and an audit reason', function () {
    $owner = stockAdjustmentUser(Role::OWNER);
    $product = adjustableProduct();

    $this->actingAs($owner);
    Livewire::test('pages::owner.stock-adjustments')
        ->set('productId', (string) $product->id)
        ->set('actualStock', '-1')
        ->set('note', '')
        ->call('save')
        ->assertHasErrors(['actualStock', 'note']);

    expect($product->refresh()->stock)->toBe(10)
        ->and(InventoryMovement::query()->count())->toBe(0);
});

test('cashier cannot invoke stock adjustment actions directly', function () {
    $cashier = stockAdjustmentUser(Role::CASHIER);
    $product = adjustableProduct();

    $this->actingAs($cashier);
    Livewire::test('pages::owner.stock-adjustments')
        ->assertForbidden();

    expect($product->refresh()->stock)->toBe(10)
        ->and(InventoryMovement::query()->count())->toBe(0);
});
