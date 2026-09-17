<?php

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function historyUser(string $role): User
{
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', $role)->firstOrFail())->save();

    return $user;
}

function historySale(User $cashier, string $invoice): Sale
{
    $category = Category::create(['name' => 'Makanan '.$invoice]);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'SKU-'.$invoice,
        'name' => 'Produk '.$invoice,
        'selling_price' => 5000,
        'stock' => 10,
        'is_active' => true,
    ]);
    $sale = Sale::create([
        'invoice_number' => $invoice,
        'user_id' => $cashier->id,
        'transaction_date' => now()->toDateString(),
        'subtotal' => 10000,
        'discount' => 0,
        'total' => 10000,
        'status' => Sale::STATUS_PAID,
    ]);
    SaleItem::create(['sale_id' => $sale->id, 'product_id' => $product->id, 'quantity' => 2, 'unit_price' => 5000, 'subtotal' => 10000]);
    Payment::create(['sale_id' => $sale->id, 'method' => Payment::METHOD_CASH, 'amount' => 10000, 'received_amount' => 10000, 'change_amount' => 0, 'status' => Payment::STATUS_PAID, 'paid_at' => now(), 'confirmed_by' => $cashier->id]);

    return $sale;
}

test('owner and cashier can access sales history', function () {
    $this->actingAs(historyUser(Role::OWNER))->get(route('sales.history'))->assertSuccessful();
    $this->actingAs(historyUser(Role::CASHIER))->get(route('sales.history'))->assertSuccessful();
});

test('cashier sees only their own transactions', function () {
    $cashier = historyUser(Role::CASHIER);
    $otherCashier = historyUser(Role::CASHIER);
    historySale($cashier, 'INV-OWN-0001');
    historySale($otherCashier, 'INV-OTHER-0001');
    $this->actingAs($cashier);

    Livewire::test('pages::sales.history')
        ->assertSee('INV-OWN-0001')
        ->assertDontSee('INV-OTHER-0001');
});

test('owner can view transaction detail', function () {
    $owner = historyUser(Role::OWNER);
    $cashier = historyUser(Role::CASHIER);
    $sale = historySale($cashier, 'INV-DETAIL-0001');
    $this->actingAs($owner);

    Livewire::test('pages::sales.history')
        ->call('viewDetails', $sale->id)
        ->assertSee('Produk INV-DETAIL-0001')
        ->assertSee('Rp10.000');
});

test('qris transaction detail shows its method without cash change fields', function () {
    $cashier = historyUser(Role::CASHIER);
    $sale = historySale($cashier, 'INV-QRIS-0001');
    $sale->payments()->firstOrFail()->update([
        'method' => Payment::METHOD_QRIS,
        'status' => Payment::STATUS_CONFIRMED,
        'received_amount' => null,
        'change_amount' => 0,
    ]);
    $this->actingAs($cashier);

    Livewire::test('pages::sales.history')
        ->call('viewDetails', $sale->id)
        ->assertSee('QRIS')
        ->assertDontSee('Tunai')
        ->assertDontSee('Uang diterima')
        ->assertDontSee('Kembalian');
});
