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

function productOwner(): User
{
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    return $owner;
}

test('only owners can access product management', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)
        ->get(route('owner.products'))
        ->assertForbidden();

    $this->actingAs(productOwner())
        ->get(route('owner.products'))
        ->assertSuccessful();
});

test('owner can create a product', function () {
    $this->actingAs(productOwner());
    $category = Category::create(['name' => 'Makanan']);

    Livewire::test('pages::owner.products')
        ->set('sku', 'BRG-001')
        ->set('name', 'Indomie Goreng')
        ->set('categoryId', (string) $category->id)
        ->set('sellingPrice', '3500')
        ->set('stock', '24')
        ->call('save')
        ->assertHasNoErrors();

    $product = Product::where('sku', 'BRG-001')->firstOrFail();

    expect($product->name)->toBe('Indomie Goreng')
        ->and($product->category->is($category))->toBeTrue();
});

test('product sku must be unique', function () {
    $this->actingAs(productOwner());
    $category = Category::create(['name' => 'Minuman']);
    Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-001',
        'name' => 'Aqua 600 ml',
        'selling_price' => 4000,
        'stock' => 18,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.products')
        ->set('sku', 'BRG-001')
        ->set('name', 'Teh Botol Sosro')
        ->set('categoryId', (string) $category->id)
        ->set('sellingPrice', '5000')
        ->set('stock', '10')
        ->call('save')
        ->assertHasErrors(['sku']);
});

test('owner can edit and delete a product', function () {
    $this->actingAs(productOwner());
    $category = Category::create(['name' => 'Perawatan']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-002',
        'name' => 'Rinso 800 gram',
        'selling_price' => 24000,
        'stock' => 0,
        'is_active' => true,
    ]);

    Livewire::test('pages::owner.products')
        ->call('edit', $product->id)
        ->set('name', 'Rinso 1 kg')
        ->set('stock', '12')
        ->call('save')
        ->call('confirmDelete', $product->id)
        ->call('deleteConfirmed')
        ->assertHasNoErrors();

    expect(Product::where('sku', 'BRG-002')->exists())->toBeFalse();
});
