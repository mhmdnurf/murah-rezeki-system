<?php

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportService;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->owner->role()->associate(Role::create(['name' => 'Owner', 'slug' => Role::OWNER]))->save();
    $this->cashier = User::factory()->create();
    $this->cashier->role()->associate(Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]))->save();
});

function reportSale(User $cashier, string $invoice, string $date, string $status = 'PAID', string $method = 'CASH'): Sale
{
    $sale = Sale::create(['invoice_number' => $invoice, 'user_id' => $cashier->id, 'transaction_date' => $date, 'subtotal' => 12500, 'discount' => 2500, 'total' => 10000, 'status' => $status]);
    Payment::create(['sale_id' => $sale->id, 'method' => $method, 'amount' => 10000, 'received_amount' => 10000, 'change_amount' => 0, 'status' => 'PAID', 'paid_at' => now(), 'confirmed_by' => $cashier->id]);

    return $sale;
}

test('only owners can access reports and downloads', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
    $this->actingAs($this->cashier)->get(route($route))->assertForbidden();
})->with(['owner.reports', 'owner.reports.pdf', 'owner.reports.excel']);

test('owner sees report with default period and navigation', function () {
    $this->actingAs($this->owner)->get(route('owner.reports'))->assertSuccessful()->assertSee('Laporan Penjualan')->assertSee('Unduh PDF')->assertSee('Unduh Excel');
});

test('reports filter transaction dates inclusively and export applied filters', function () {
    reportSale($this->cashier, 'START', '2026-08-01');
    reportSale($this->cashier, 'END', '2026-08-31');
    reportSale($this->cashier, 'OUTSIDE', '2026-09-01');
    $this->actingAs($this->owner);

    Livewire::test('pages::owner.reports')->set('startDate', '2026-08-01')->set('endDate', '2026-08-31')
        ->call('applyFilters')->assertHasNoErrors()->assertSee('START')->assertSee('END')->assertDontSee('OUTSIDE')
        ->assertSee('Rp20.000')->assertSet('filters.startDate', '2026-08-01');
});

test('reports combine cashier payment and status filters without duplicating sales', function () {
    $sale = reportSale($this->cashier, 'MATCH', '2026-08-15', 'PAID', 'QRIS');
    $sale->payments()->create(['method' => 'QRIS', 'amount' => 0, 'received_amount' => 0, 'change_amount' => 0, 'status' => 'PAID']);
    reportSale($this->owner, 'OTHER-CASHIER', '2026-08-15', 'PAID', 'QRIS');
    reportSale($this->cashier, 'OTHER-METHOD', '2026-08-15');
    reportSale($this->cashier, 'OTHER-STATUS', '2026-08-15', 'CANCELLED', 'QRIS');
    $this->actingAs($this->owner);

    Livewire::test('pages::owner.reports')->set('startDate', '2026-08-01')->set('endDate', '2026-08-31')
        ->set('cashierId', (string) $this->cashier->id)->set('paymentMethod', 'QRIS')->call('applyFilters')
        ->assertSee('MATCH')->assertDontSee('OTHER-CASHIER')->assertDontSee('OTHER-METHOD')->assertDontSee('OTHER-STATUS');
    expect(app(ReportService::class)->summary(['startDate' => '2026-08-01', 'endDate' => '2026-08-31', 'cashierId' => $this->cashier->id, 'paymentMethod' => 'QRIS', 'status' => 'PAID']))->toMatchArray(['transactions' => 1, 'revenue' => '10000']);
});

test('draft and cancelled totals are excluded from revenue', function () {
    reportSale($this->cashier, 'PAID', '2026-08-15');
    reportSale($this->cashier, 'DRAFT', '2026-08-15', 'DRAFT');
    reportSale($this->cashier, 'CANCELLED', '2026-08-15', 'CANCELLED');
    expect(app(ReportService::class)->summary(['startDate' => '2026-08-01', 'endDate' => '2026-08-31']))->toMatchArray(['transactions' => 3, 'revenue' => '10000']);
});

test('invalid filters are rejected by page and exports', function (string $field, string $value) {
    $this->actingAs($this->owner);
    Livewire::test('pages::owner.reports')->set($field, $value)->call('applyFilters')->assertHasErrors($field);
    $filters = array_replace(app(ReportService::class)->defaults(), [$field => $value]);
    foreach (['owner.reports.pdf', 'owner.reports.excel'] as $route) {
        $this->getJson(route($route, $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
    }
})->with([['startDate', 'invalid'], ['endDate', '2000-01-01'], ['cashierId', '999999'], ['paymentMethod', 'invalid'], ['status', 'invalid']]);

test('empty report and reset filters work', function () {
    $this->actingAs($this->owner);
    Livewire::test('pages::owner.reports')->assertSee('Tidak ada transaksi pada filter ini.')
        ->set('startDate', 'invalid')->call('applyFilters')->assertHasErrors('startDate')
        ->call('resetFilters')->assertHasNoErrors()->assertSet('startDate', now()->startOfMonth()->toDateString());
});

test('pagination keeps totals for all matches and resets when filters change', function () {
    foreach (range(1, 21) as $number) {
        reportSale($this->cashier, 'INV-PAGE-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), now()->toDateString());
    }
    $this->actingAs($this->owner);
    Livewire::test('pages::owner.reports')->assertSee('Rp210.000')
        ->assertSee('INV-PAGE-021')->assertDontSee('INV-PAGE-001')
        ->call('gotoPage', 2)->assertSee('INV-PAGE-001')->assertDontSee('INV-PAGE-021')
        ->set('paymentMethod', 'QRIS')->call('applyFilters')->assertSet('paginators.page', 1)
        ->assertSee('Tidak ada transaksi pada filter ini.');
});

test('report counts quantities rather than product lines and uses sale totals after discount', function () {
    $sale = reportSale($this->cashier, 'INV-QUANTITY', now()->toDateString());
    $category = Category::create(['name' => 'Report category']);
    $product = Product::create(['category_id' => $category->id, 'sku' => 'REPORT-PRODUCT', 'name' => 'Report product', 'selling_price' => 2500, 'stock' => 10, 'is_active' => true]);
    $sale->items()->create(['product_id' => $product->id, 'quantity' => 5, 'unit_price' => 2500, 'subtotal' => 12500]);
    $reports = app(ReportService::class);
    expect($reports->summary($reports->defaults()))->toMatchArray(['transactions' => 1, 'items' => 5, 'revenue' => '10000']);
    expect($reports->row($reports->details($reports->defaults())->first()))->toMatchArray([3 => 5, 4 => 10000.0]);
});

test('pdf download contains a valid document including empty reports', function (bool $withSales) {
    if ($withSales) {
        reportSale($this->cashier, 'PDF-INVOICE', now()->toDateString());
    }
    $this->actingAs($this->owner);
    $response = $this->get(route('owner.reports.pdf', app(ReportService::class)->defaults()));
    $response->assertSuccessful()->assertHeader('Content-Type', 'application/pdf')->assertDownload();
    expect($response->getContent())->toStartWith('%PDF-')->toContain('%%EOF');
})->with([true, false]);

test('excel includes all filtered rows and preserves strings without formulas', function () {
    $this->cashier->update(['name' => '=1+1']);
    foreach (range(1, 21) as $number) {
        reportSale($this->cashier, 'XLSX-'.str_pad((string) $number, 3, '0', STR_PAD_LEFT), '2026-08-31');
    }
    reportSale($this->cashier, 'EXCLUDED', '2026-09-01');
    $this->actingAs($this->owner);
    $filters = array_replace(app(ReportService::class)->defaults(), ['startDate' => '2026-08-01', 'endDate' => '2026-08-31']);
    $response = $this->get(route('owner.reports.excel', $filters));
    $response->assertSuccessful()->assertDownload('laporan-penjualan-2026-08-01-2026-08-31.xlsx');
    $path = tempnam(sys_get_temp_dir(), 'report-test-');
    try {
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        expect($sheet->getCell('B8')->getValue())->toBe(21)
            ->and($sheet->getCell('B9')->getValue())->toBe(210000.0)
            ->and($sheet->getCell('A15')->getValue())->toBe('XLSX-021')
            ->and($sheet->getCell('A35')->getValue())->toBe('XLSX-001')
            ->and($sheet->getCell('C15')->getValue())->toBe('=1+1')
            ->and($sheet->getCell('C15')->getDataType())->toBe(DataType::TYPE_STRING)
            ->and($sheet->getCell('E15')->getDataType())->toBe(DataType::TYPE_NUMERIC);
        $spreadsheet->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});

test('excel empty report is a readable workbook', function () {
    $this->actingAs($this->owner);
    $response = $this->get(route('owner.reports.excel', app(ReportService::class)->defaults()));
    $response->assertSuccessful();
    $path = tempnam(sys_get_temp_dir(), 'report-test-');
    try {
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        expect($spreadsheet->getActiveSheet()->getCell('A15')->getValue())->toBe('Tidak ada transaksi pada filter ini.');
        $spreadsheet->disconnectWorksheets();
    } finally {
        unlink($path);
    }
});
