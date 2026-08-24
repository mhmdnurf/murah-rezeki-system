<?php

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function salesCashier(): User
{
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    return $cashier;
}

test('only cashiers can access sales management', function () {
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    $this->actingAs($owner)->get(route('cashier.sales'))->assertForbidden();
    $this->actingAs(salesCashier())->get(route('cashier.sales'))->assertSuccessful();
});

test('cashier can complete a cash sale and reduce stock', function () {
    $cashier = salesCashier();
    $this->actingAs($cashier);
    $category = Category::create(['name' => 'Makanan']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-001',
        'name' => 'Indomie Goreng',
        'selling_price' => 3500,
        'stock' => 10,
        'is_active' => true,
    ]);

    Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->set('receivedAmount', '10000')
        ->call('saveSale')
        ->assertHasNoErrors();

    $sale = Sale::query()->with('items')->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    expect($sale->total)->toEqual('7000.00')
        ->and($sale->items->first()->quantity)->toBe(2)
        ->and($payment->change_amount)->toEqual('3000.00')
        ->and($product->refresh()->stock)->toBe(8)
        ->and(InventoryMovement::query()->where('reference_type', 'sale')->count())->toBe(1);
});

test('cashier cannot sell more than available stock', function () {
    $this->actingAs(salesCashier());
    $category = Category::create(['name' => 'Minuman']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-002',
        'name' => 'Aqua 600 ml',
        'selling_price' => 4000,
        'stock' => 1,
        'is_active' => true,
    ]);

    Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->assertHasErrors(['cart']);
});

test('cashier must receive enough money to complete a sale', function () {
    $this->actingAs(salesCashier());
    $category = Category::create(['name' => 'Perawatan']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-003',
        'name' => 'Rinso 800 gram',
        'selling_price' => 24000,
        'stock' => 5,
        'is_active' => true,
    ]);

    Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('receivedAmount', '10000')
        ->call('saveSale')
        ->assertHasErrors(['receivedAmount']);

    expect(Sale::query()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(5);
});
