<?php

use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Transaksi Penjualan')] class extends Component
{
    public string $search = '';

    public string $receivedAmount = '';

    public array $cart = [];

    /**
     * Get active products that can be sold.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock', '>', 0)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('sku', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->with('category')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the current cart subtotal.
     */
    public function cartSubtotal(): int
    {
        return (int) collect($this->cart)->sum(fn (array $item): int => $item['unit_price'] * $item['quantity']);
    }

    /**
     * Get the number of units in the cart.
     */
    public function cartQuantity(): int
    {
        return (int) collect($this->cart)->sum('quantity');
    }

    /**
     * Add a product to the cart or increase its quantity.
     */
    public function addToCart(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);
        $currentQuantity = $this->cart[$productId]['quantity'] ?? 0;

        if (! $product->is_active || $product->stock < $currentQuantity + 1) {
            $this->addError('cart', 'Jumlah barang melebihi stok yang tersedia.');

            return;
        }

        $this->resetValidation('cart');
        $this->cart[$productId] = [
            'product_id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'unit_price' => (int) $product->selling_price,
            'quantity' => $currentQuantity + 1,
        ];
    }

    /**
     * Increase a cart item quantity.
     */
    public function increaseQuantity(int $productId): void
    {
        $this->addToCart($productId);
    }

    /**
     * Decrease a cart item quantity.
     */
    public function decreaseQuantity(int $productId): void
    {
        if (! isset($this->cart[$productId])) {
            return;
        }

        if ($this->cart[$productId]['quantity'] === 1) {
            $this->removeFromCart($productId);

            return;
        }

        $this->cart[$productId]['quantity']--;
    }

    /**
     * Remove a product from the cart.
     */
    public function removeFromCart(int $productId): void
    {
        unset($this->cart[$productId]);
    }

    /**
     * Save the cash sale and update inventory atomically.
     */
    public function saveSale(): void
    {
        if ($this->cart === []) {
            $this->addError('cart', 'Tambahkan minimal satu barang ke keranjang.');

            return;
        }

        $validated = $this->validate([
            'receivedAmount' => ['required', 'numeric', 'min:0'],
        ]);
        $total = $this->cartSubtotal();
        $receivedAmount = (int) $validated['receivedAmount'];

        if ($receivedAmount < $total) {
            $this->addError('receivedAmount', 'Uang yang diterima belum mencukupi total transaksi.');

            return;
        }

        $sale = DB::transaction(function () use ($receivedAmount, $total): ?Sale {
            $productIds = array_map('intval', array_keys($this->cart));
            $products = Product::query()->whereKey($productIds)->lockForUpdate()->get()->keyBy('id');

            foreach ($this->cart as $item) {
                $product = $products->get((int) $item['product_id']);

                if ($product === null || ! $product->is_active || $product->stock < $item['quantity']) {
                    return null;
                }
            }

            $sale = Sale::create([
                'invoice_number' => 'TEMP-'.Str::uuid(),
                'user_id' => auth()->id(),
                'transaction_date' => now()->toDateString(),
                'subtotal' => $total,
                'discount' => 0,
                'total' => $total,
                'status' => Sale::STATUS_PAID,
            ]);
            $sale->update(['invoice_number' => 'INV-'.now()->format('Ymd').'-'.str_pad((string) $sale->id, 4, '0', STR_PAD_LEFT)]);

            foreach ($this->cart as $item) {
                $product = $products->get((int) $item['product_id']);
                $itemSubtotal = $item['unit_price'] * $item['quantity'];

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $itemSubtotal,
                ]);

                $product->decrement('stock', $item['quantity']);

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'type' => InventoryMovement::TYPE_OUT,
                    'quantity' => $item['quantity'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'note' => 'Penjualan '.$sale->invoice_number,
                    'created_by' => auth()->id(),
                ]);
            }

            Payment::create([
                'sale_id' => $sale->id,
                'method' => Payment::METHOD_CASH,
                'amount' => $total,
                'received_amount' => $receivedAmount,
                'change_amount' => $receivedAmount - $total,
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'confirmed_by' => auth()->id(),
            ]);

            return $sale;
        });

        if ($sale === null) {
            $this->addError('cart', 'Stok berubah atau tidak mencukupi. Periksa kembali keranjang.');

            return;
        }

        $this->reset(['cart', 'receivedAmount']);
        Flux::toast(variant: 'success', text: 'Transaksi '.$sale->invoice_number.' berhasil disimpan.');
    }
};
?>

<section class="space-y-5">
    <header class="border-b border-slate-200 pb-5"><flux:heading size="xl" class="tracking-tight text-slate-900">Transaksi Penjualan</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Kasir / Transaksi Penjualan</flux:text></header>

    <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_390px]">
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40"><div class="border-b border-slate-200 p-4 sm:p-5"><flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari nama atau kode barang" aria-label="Cari barang" /></div><div class="p-4 sm:p-5"><div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@forelse ($this->products as $product)<button type="button" class="rounded-lg border border-slate-200 p-4 text-left transition hover:border-sky-400 hover:bg-sky-50" wire:click="addToCart({{ $product->id }})" wire:key="sale-product-{{ $product->id }}"><div class="flex items-start justify-between gap-3"><div><p class="font-semibold text-slate-700">{{ $product->name }}</p><p class="mt-1 font-mono text-[10px] text-slate-400">{{ $product->sku }}</p></div><flux:icon name="plus-circle" class="size-5 shrink-0 text-sky-500" /></div><div class="mt-4 flex items-center justify-between gap-2 text-xs"><span class="font-semibold text-slate-700">Rp{{ number_format((int) $product->selling_price, 0, ',', '.') }}</span><span class="text-slate-400">Stok {{ $product->stock }}</span></div></button>@empty<div class="col-span-full px-5 py-12 text-center"><flux:icon name="cube" class="mx-auto size-8 text-slate-300" /><flux:heading size="sm" class="mt-3">Barang tidak ditemukan</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Pastikan barang aktif dan memiliki stok.</flux:text></div>@endforelse</div></div></div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40"><div class="border-b border-slate-200 p-4 sm:p-5"><div class="flex items-center justify-between gap-3"><flux:heading size="sm">Keranjang</flux:heading><flux:badge size="sm" color="sky">{{ $this->cartQuantity() }} item</flux:badge></div></div><div class="p-4 sm:p-5">@if ($this->cart === [])<div class="py-10 text-center"><flux:icon name="shopping-cart" class="mx-auto size-8 text-slate-300" /><flux:text class="mt-3 text-xs text-slate-500">Keranjang masih kosong.</flux:text></div>@else<div class="space-y-4">@foreach ($this->cart as $item)<div class="flex gap-3" wire:key="cart-item-{{ $item['product_id'] }}"><div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-slate-700">{{ $item['name'] }}</p><p class="mt-1 text-xs text-slate-400">Rp{{ number_format($item['unit_price'], 0, ',', '.') }}</p><div class="mt-2 flex items-center gap-2"><flux:button size="xs" variant="ghost" wire:click="decreaseQuantity({{ $item['product_id'] }})">−</flux:button><span class="min-w-5 text-center text-xs font-semibold text-slate-700">{{ $item['quantity'] }}</span><flux:button size="xs" variant="ghost" wire:click="increaseQuantity({{ $item['product_id'] }})">+</flux:button></div></div><div class="text-right"><p class="text-sm font-semibold text-slate-700">Rp{{ number_format($item['unit_price'] * $item['quantity'], 0, ',', '.') }}</p><button type="button" class="mt-2 text-xs text-red-500 hover:text-red-700" wire:click="removeFromCart({{ $item['product_id'] }})">Hapus</button></div></div>@endforeach</div>@endif<flux:error name="cart" /><div class="mt-5 border-t border-slate-200 pt-4"><div class="flex items-center justify-between text-sm font-semibold text-slate-800"><span>Total</span><span>Rp{{ number_format($this->cartSubtotal(), 0, ',', '.') }}</span></div><div class="mt-4"><flux:field><flux:label>Uang Diterima</flux:label><flux:input wire:model="receivedAmount" type="number" min="0" step="100" placeholder="0" /><flux:error name="receivedAmount" /></flux:field></div><flux:button type="button" variant="primary" class="mt-4 w-full !bg-sky-500 hover:!bg-sky-600" wire:click="saveSale" wire:loading.attr="disabled">Bayar dan Simpan</flux:button></div></div></div>
    </div>
</section>
