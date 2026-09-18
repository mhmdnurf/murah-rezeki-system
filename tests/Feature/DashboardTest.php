<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

afterEach(function () {
    CarbonImmutable::setTestNow();
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('owner dashboard displays dynamic store statistics', function () {
    CarbonImmutable::setTestNow('2026-09-18 10:00:00');

    $ownerRole = Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    $owner = User::factory()->create(['role_id' => $ownerRole->id]);
    $category = Category::create(['name' => 'Minuman']);
    $lowStockProduct = Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-001',
        'name' => 'Air Mineral',
        'selling_price' => 5000,
        'stock' => 4,
        'is_active' => true,
    ]);
    Product::create([
        'category_id' => $category->id,
        'sku' => 'BRG-002',
        'name' => 'Teh Botol',
        'selling_price' => 7000,
        'stock' => 20,
        'is_active' => true,
    ]);

    $todaySale = Sale::create([
        'invoice_number' => 'INV-TODAY',
        'user_id' => $owner->id,
        'transaction_date' => '2026-09-18',
        'subtotal' => 25000,
        'discount' => 0,
        'total' => 25000,
        'status' => Sale::STATUS_PAID,
    ]);
    SaleItem::create([
        'sale_id' => $todaySale->id,
        'product_id' => $lowStockProduct->id,
        'quantity' => 5,
        'unit_price' => 5000,
        'subtotal' => 25000,
    ]);
    Sale::create([
        'invoice_number' => 'INV-YESTERDAY',
        'user_id' => $owner->id,
        'transaction_date' => '2026-09-17',
        'subtotal' => 10000,
        'discount' => 0,
        'total' => 10000,
        'status' => Sale::STATUS_PAID,
    ]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertSuccessful()
        ->assertViewHas('todayRevenue', 25000)
        ->assertViewHas('yesterdayRevenue', 10000)
        ->assertViewHas('todayTransactions', 1)
        ->assertViewHas('totalProducts', 2)
        ->assertViewHas('totalCategories', 1)
        ->assertViewHas('lowStockCount', 1)
        ->assertViewHas('weeklySalesTotal', 35000)
        ->assertViewHas('weeklySales', fn (Collection $sales): bool => $sales->last()['total'] === 25000)
        ->assertSee('INV-TODAY')
        ->assertSee('Air Mineral');
});

test('cashier dashboard only includes sales created by that cashier', function () {
    CarbonImmutable::setTestNow('2026-09-18 10:00:00');

    $cashierRole = Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
    $cashier = User::factory()->create(['role_id' => $cashierRole->id]);
    $otherCashier = User::factory()->create(['role_id' => $cashierRole->id]);

    Sale::create([
        'invoice_number' => 'INV-OWN-SALE',
        'user_id' => $cashier->id,
        'transaction_date' => '2026-09-18',
        'subtotal' => 15000,
        'discount' => 0,
        'total' => 15000,
        'status' => Sale::STATUS_PAID,
    ]);
    Sale::create([
        'invoice_number' => 'INV-OTHER-SALE',
        'user_id' => $otherCashier->id,
        'transaction_date' => '2026-09-18',
        'subtotal' => 90000,
        'discount' => 0,
        'total' => 90000,
        'status' => Sale::STATUS_PAID,
    ]);

    $this->actingAs($cashier)
        ->get(route('cashier.dashboard'))
        ->assertSuccessful()
        ->assertViewHas('todayRevenue', 15000)
        ->assertViewHas('todayTransactions', 1)
        ->assertViewHas('weeklySalesTotal', 15000)
        ->assertSee('INV-OWN-SALE')
        ->assertDontSee('INV-OTHER-SALE');
});
