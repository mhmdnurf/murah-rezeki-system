<?php

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function receiptUser(string $role): User
{
    $user = User::factory()->create();
    $user->role()->associate(Role::where('slug', $role)->firstOrFail())->save();

    return $user;
}

function receiptSale(User $cashier, string $paymentMethod = Payment::METHOD_CASH): Sale
{
    $product = Product::create([
        'category_id' => Category::create(['name' => 'Produk Struk'])->id,
        'sku' => 'STRUK-001',
        'name' => 'Beras Premium',
        'selling_price' => 6000,
        'stock' => 10,
        'is_active' => true,
    ]);
    $sale = Sale::create([
        'invoice_number' => 'INV-RECEIPT-0001',
        'user_id' => $cashier->id,
        'transaction_date' => now()->toDateString(),
        'subtotal' => 15000,
        'discount' => 0,
        'total' => 15000,
        'status' => Sale::STATUS_PAID,
    ]);

    SaleItem::create([
        'sale_id' => $sale->id,
        'product_id' => $product->id,
        'quantity' => 3,
        'unit_price' => 5000,
        'subtotal' => 15000,
    ]);
    Payment::create([
        'sale_id' => $sale->id,
        'method' => $paymentMethod,
        'amount' => 15000,
        'received_amount' => $paymentMethod === Payment::METHOD_CASH ? 20000 : null,
        'change_amount' => $paymentMethod === Payment::METHOD_CASH ? 5000 : 0,
        'status' => $paymentMethod === Payment::METHOD_CASH ? Payment::STATUS_PAID : Payment::STATUS_CONFIRMED,
        'paid_at' => now(),
        'confirmed_by' => $cashier->id,
    ]);

    return $sale;
}

test('guest must log in before viewing a receipt', function () {
    $cashier = receiptUser(Role::CASHIER);
    $sale = receiptSale($cashier);

    $this->get(route('sales.receipt', $sale))
        ->assertRedirect(route('login'));
});

test('cashier can view and print their own cash receipt', function () {
    $cashier = receiptUser(Role::CASHIER);
    $sale = receiptSale($cashier);

    $this->actingAs($cashier)
        ->get(route('sales.receipt', $sale))
        ->assertSuccessful()
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
        ->assertSee('Jl. Brigjen Katamso, Tanjungpinang')
        ->assertSee('INV-RECEIPT-0001')
        ->assertSee('Beras Premium')
        ->assertSee('3 × Rp5.000')
        ->assertSee('Rp15.000')
        ->assertSee('Uang diterima')
        ->assertSee('Rp20.000')
        ->assertSee('Kembalian')
        ->assertSee('Cetak Struk')
        ->assertSee('data-print-receipt', false);
});

test('receipt keeps the item sale price after the product price changes', function () {
    $cashier = receiptUser(Role::CASHIER);
    $sale = receiptSale($cashier);
    $sale->items->firstOrFail()->product->update(['selling_price' => 9000]);

    $this->actingAs($cashier)
        ->get(route('sales.receipt', $sale))
        ->assertSuccessful()
        ->assertSee('3 × Rp5.000')
        ->assertDontSee('3 × Rp9.000');
});

test('qris receipt shows manual confirmation without cash fields', function () {
    $cashier = receiptUser(Role::CASHIER);
    $sale = receiptSale($cashier, Payment::METHOD_QRIS);

    $this->actingAs($cashier)
        ->get(route('sales.receipt', $sale))
        ->assertSuccessful()
        ->assertSee('QRIS')
        ->assertSee('Dikonfirmasi kasir')
        ->assertDontSee('Uang diterima')
        ->assertDontSee('Kembalian');
});

test('owner can view every receipt while cashiers cannot view another cashier receipt', function () {
    $owner = receiptUser(Role::OWNER);
    $cashier = receiptUser(Role::CASHIER);
    $otherCashier = receiptUser(Role::CASHIER);
    $sale = receiptSale($cashier);

    $this->actingAs($owner)
        ->get(route('sales.receipt', $sale))
        ->assertSuccessful();

    $this->actingAs($otherCashier)
        ->get(route('sales.receipt', $sale))
        ->assertForbidden();
});
