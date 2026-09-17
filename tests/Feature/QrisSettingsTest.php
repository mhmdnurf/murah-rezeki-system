<?php

use App\Models\Category;
use App\Models\Payment;
use App\Models\Product;
use App\Models\QrisSetting;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\QrisSettingSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
    $this->owner = User::factory()->create();
    $this->owner->role()->associate(Role::create(['name' => 'Owner', 'slug' => Role::OWNER]))->save();
    $this->cashier = User::factory()->create();
    $this->cashier->role()->associate(Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]))->save();
});

test('only owner can access qris settings', function () {
    $this->get(route('owner.qris'))->assertRedirect();
    $this->actingAs($this->cashier)->get(route('owner.qris'))->assertForbidden();
    Livewire::test('pages::owner.qris')->assertForbidden();
    $this->actingAs($this->owner)->get(route('owner.qris'))->assertSuccessful();
});

test('owner can upload replace and deactivate the single store qris', function () {
    $this->actingAs($this->owner);
    $component = Livewire::test('pages::owner.qris')
        ->set('merchantName', 'Merchant Toko')
        ->set('qrImage', UploadedFile::fake()->image('qris.png'))
        ->set('isActive', true)
        ->call('save')->assertHasNoErrors();

    $setting = QrisSetting::query()->firstOrFail();
    $oldPath = $setting->qr_image;
    Storage::disk('public')->assertExists($oldPath);
    expect($setting->merchant_name)->toBe('Merchant Toko')->and($setting->is_active)->toBeTrue();

    $component->set('merchantName', 'Merchant Baru')
        ->set('qrImage', UploadedFile::fake()->image('new.jpg'))
        ->call('save')->assertHasNoErrors();
    $setting->refresh();
    expect(QrisSetting::query()->count())->toBe(1)
        ->and($setting->qr_image)->not->toBe($oldPath);
    Storage::disk('public')->assertExists([$oldPath, $setting->qr_image]);

    $component->set('isActive', false)->call('save')->assertHasNoErrors();
    expect($setting->refresh()->is_active)->toBeFalse();
    Livewire::test('pages::owner.qris')->assertSet('merchantName', 'Merchant Baru')->assertSet('isActive', false);
});

test('qris cannot be activated without an image', function () {
    $this->actingAs($this->owner);
    Livewire::test('pages::owner.qris')->set('isActive', true)->call('save')->assertHasErrors('qrImage');
    expect(QrisSetting::query()->count())->toBe(0);
});

test('invalid uploads are rejected', function (string $kind) {
    $this->actingAs($this->owner);
    $file = match ($kind) {
        'not image' => UploadedFile::fake()->create('qris.txt', 10, 'text/plain'),
        'svg' => UploadedFile::fake()->createWithContent('qris.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'oversized' => UploadedFile::fake()->image('qris.png')->size(2049),
    };
    Livewire::test('pages::owner.qris')->set('qrImage', $file)->call('save')->assertHasErrors('qrImage');
    expect(QrisSetting::query()->count())->toBe(0);
})->with(['not image', 'svg', 'oversized']);

test('save action rechecks owner authorization', function () {
    $this->actingAs($this->owner);
    $component = Livewire::test('pages::owner.qris');
    $this->actingAs($this->cashier);
    $component->call('save')->assertForbidden();
    expect(QrisSetting::query()->count())->toBe(0);
});

test('cashier sees active image or physical qris fallback', function (string $state) {
    if ($state !== 'missing') {
        $setting = QrisSetting::factory()->create([
            'id' => QrisSetting::STORE_ID,
            'merchant_name' => 'Merchant Pengujian',
            'qr_image' => 'qris/test.png',
            'is_active' => $state !== 'inactive',
        ]);
        if ($state !== 'missing file') {
            Storage::disk('public')->put($setting->qr_image, 'fixture');
        }
    }
    $product = Product::create([
        'category_id' => Category::create(['name' => 'QRIS'])->id,
        'sku' => 'QRIS-ITEM', 'name' => 'Produk', 'selling_price' => 5000, 'stock' => 3, 'is_active' => true,
    ]);
    $this->actingAs($this->cashier);
    $component = Livewire::test('pages::cashier.sales')
        ->call('addToCart', $product->id)
        ->set('paymentMethod', Payment::METHOD_QRIS)
        ->call('saveSale')->assertHasNoErrors();

    if ($state === 'active') {
        $component->assertSee('Merchant Pengujian')->assertSeeHtml(Storage::disk('public')->url('qris/test.png'));
    } else {
        $component->assertSet('qrisDisplay', null)->assertSee('QRIS fisik toko')->assertDontSee('Merchant Pengujian');
    }

    $component->call('confirmQrisPayment')->assertHasNoErrors()->assertSet('qrisDisplay', null);
    expect(Payment::query()->firstOrFail()->method)->toBe(Payment::METHOD_QRIS);
})->with(['active', 'inactive', 'missing', 'missing file']);

test('seeding does not overwrite owner qris settings', function () {
    $this->seed(QrisSettingSeeder::class);
    QrisSetting::query()->findOrFail(QrisSetting::STORE_ID)->update(['merchant_name' => 'Merchant Owner']);
    $this->seed(QrisSettingSeeder::class);
    expect(QrisSetting::query()->count())->toBe(1)
        ->and(QrisSetting::query()->firstOrFail()->merchant_name)->toBe('Merchant Owner');
});
