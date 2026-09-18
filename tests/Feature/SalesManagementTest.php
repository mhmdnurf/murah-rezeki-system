<?php

use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Support\Str;
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
    $initialInvoiceNumber = null;
    Sale::creating(function (Sale $sale) use (&$initialInvoiceNumber): void {
        $initialInvoiceNumber = $sale->invoice_number;
    });

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

    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->call('addToCart', $product->id)
        ->set('receivedAmount', '10000')
        ->call('saveSale')
        ->assertHasNoErrors()
        ->assertSet('cart', [])
        ->assertSet('receivedAmount', '');

    $sale = Sale::query()->with('items')->firstOrFail();
    $payment = Payment::query()->firstOrFail();

    $component->assertRedirect(route('sales.receipt', $sale));

    expect($initialInvoiceNumber)->toBeString()->not->toBeEmpty()
        ->and(Str::length($initialInvoiceNumber))->toBeLessThanOrEqual(30)
        ->and($sale->invoice_number)->toMatch('/^INV-\d{8}-\d{4,}$/')
        ->and($sale->total)->toEqual('7000.00')
        ->and($sale->items->first()->quantity)->toBe(2)
        ->and($payment->method)->toBe(Payment::METHOD_CASH)
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

test('cashier can choose the payment method before completing a sale', function () {
    $this->actingAs(salesCashier());

    Livewire::test('pages::cashier.sales')
        ->assertSet('paymentMethod', Payment::METHOD_CASH)
        ->assertSee('Tunai')
        ->assertSee('QRIS')
        ->set('receivedAmount', '10000')
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->assertSet('paymentMethod', Payment::METHOD_QRIS)
        ->assertSet('receivedAmount', '');
});

test('qris selection does not persist a sale before manual confirmation', function () {
    $this->actingAs(salesCashier());
    $category = Category::create(['name' => 'Minuman QRIS']);
    $product = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-QRIS-001',
        'name' => 'Air Mineral QRIS',
        'selling_price' => 5000,
        'stock' => 3,
        'is_active' => true,
    ]);

    Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->call('saveSale')
        ->assertHasNoErrors()
        ->assertSet('showQrisConfirmation', true)
        ->assertSee('QRIS fisik toko')
        ->assertSet('cart.'.$product->id.'.quantity', 1);

    expect(Sale::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(3);
});

function qrisProduct(): Product
{
    return Product::create([
        'category_id' => Category::create(['name' => 'QRIS'])->id,
        'sku' => 'QRIS-TEST',
        'name' => 'Produk QRIS',
        'selling_price' => 5000,
        'stock' => 3,
        'is_active' => true,
    ]);
}

test('cashier confirms qris payment with no cash amount and updates inventory', function () {
    $cashier = salesCashier();
    $this->actingAs($cashier);
    $product = qrisProduct();

    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->call('saveSale')
        ->set('receivedAmount', '99999')
        ->call('confirmQrisPayment')
        ->assertHasNoErrors()
        ->assertSet('cart', [])
        ->assertSet('showQrisConfirmation', false)
        ->assertSet('qrisCartSnapshot', null)
        ->assertSet('paymentMethod', Payment::METHOD_CASH);

    $payment = Payment::query()->firstOrFail();
    $component->assertRedirect(route('sales.receipt', $payment->sale));

    expect($payment->method)->toBe(Payment::METHOD_QRIS)
        ->and($payment->status)->toBe(Payment::STATUS_CONFIRMED)
        ->and($payment->amount)->toEqual('5000.00')
        ->and($payment->received_amount)->toBeNull()
        ->and($payment->change_amount)->toEqual('0.00')
        ->and($payment->confirmed_by)->toBe($cashier->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->sale->status)->toBe(Sale::STATUS_PAID)
        ->and(SaleItem::query()->count())->toBe(1)
        ->and(InventoryMovement::query()->where('reference_id', $payment->sale_id)->count())->toBe(1)
        ->and($product->refresh()->stock)->toBe(2);

    $component->call('confirmQrisPayment')->assertHasErrors('paymentMethod');
    expect(Sale::query()->count())->toBe(1);
});

test('qris confirmation requires an unchanged open checkout', function (string $scenario) {
    $this->actingAs(salesCashier());
    $product = qrisProduct();
    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS);

    if ($scenario !== 'not opened') {
        $component->call('saveSale');
    }

    match ($scenario) {
        'cancelled' => $component->call('cancelQrisPayment'),
        'cart changed' => $component->call('increaseQuantity', $product->id),
        'method changed' => $component->set('paymentMethod', Payment::METHOD_CASH),
        default => null,
    };

    $component->call('confirmQrisPayment')->assertHasErrors('paymentMethod');
    expect(Sale::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(3);
})->with(['not opened', 'cancelled', 'cart changed', 'method changed']);

test('qris rechecks stock after the customer payment step', function () {
    $this->actingAs(salesCashier());
    $product = qrisProduct();
    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->call('saveSale');

    $product->update(['stock' => 0]);
    $component->call('confirmQrisPayment')->assertHasErrors('cart');
    expect(Sale::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0);
});

test('owner cannot invoke payment actions directly', function (string $action) {
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();
    $this->actingAs($owner);

    Livewire::test('pages::cashier.sales')->call($action)->assertForbidden();
})->with(['saveSale', 'confirmQrisPayment']);

test('qris payment failure rolls back sale items and stock', function () {
    $this->actingAs(salesCashier());
    $product = qrisProduct();
    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->call('saveSale');

    Payment::creating(function (): void {
        throw new RuntimeException('Payment persistence failed');
    });

    try {
        expect(fn () => $component->call('confirmQrisPayment'))->toThrow(RuntimeException::class, 'Payment persistence failed');
    } finally {
        Payment::flushEventListeners();
    }

    expect(Sale::query()->count())->toBe(0)
        ->and(SaleItem::query()->count())->toBe(0)
        ->and(Payment::query()->count())->toBe(0)
        ->and(InventoryMovement::query()->count())->toBe(0)
        ->and($product->refresh()->stock)->toBe(3);
});
