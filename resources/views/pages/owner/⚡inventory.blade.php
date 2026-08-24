<?php

use App\Models\Product;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Persediaan Barang')] class extends Component
{
    public string $search = '';

    public string $statusFilter = '';

    /**
     * Get all products for the inventory summary.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function allProducts(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()->get();
    }

    /**
     * Get filtered products with their categories.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->with('category')
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('sku', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->statusFilter !== '', function ($query): void {
                match ($this->statusFilter) {
                    'available' => $query->where('stock', '>', 10),
                    'low' => $query->whereBetween('stock', [1, 10]),
                    'out' => $query->where('stock', 0),
                    default => null,
                };
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the stock status label for a product.
     */
    public function stockStatus(Product $product): string
    {
        return match (true) {
            $product->stock === 0 => 'Habis',
            $product->stock <= 10 => 'Stok Menipis',
            default => 'Tersedia',
        };
    }

    /**
     * Get the badge color for a product stock status.
     */
    public function stockStatusColor(Product $product): string
    {
        return match ($this->stockStatus($product)) {
            'Habis' => 'red',
            'Stok Menipis' => 'amber',
            default => 'green',
        };
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Persediaan Barang</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Inventaris / Persediaan Barang</flux:text>
        </div>
    </header>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40"><flux:text class="text-xs text-slate-500">Total Barang</flux:text><flux:heading size="lg" class="mt-2 text-slate-900">{{ $this->allProducts->count() }}</flux:heading><flux:text class="mt-1 text-xs text-slate-400">Jenis barang terdaftar</flux:text></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40"><flux:text class="text-xs text-slate-500">Stok Tersedia</flux:text><flux:heading size="lg" class="mt-2 text-slate-900">{{ $this->allProducts->where('stock', '>', 10)->count() }}</flux:heading><flux:text class="mt-1 text-xs text-emerald-600">Stok dalam kondisi aman</flux:text></div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm shadow-slate-200/40"><flux:text class="text-xs text-slate-500">Perlu Perhatian</flux:text><flux:heading size="lg" class="mt-2 text-slate-900">{{ $this->allProducts->where('stock', '<=', 10)->count() }}</flux:heading><flux:text class="mt-1 text-xs text-amber-600">Menipis atau habis</flux:text></div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari barang" aria-label="Cari barang" class="w-full sm:max-w-md" />
            <div class="flex items-center gap-2">
                <flux:select wire:model.live="statusFilter" aria-label="Filter status stok" class="min-w-40">
                    <flux:select.option value="">Semua Status</flux:select.option>
                    <flux:select.option value="available">Tersedia</flux:select.option>
                    <flux:select.option value="low">Stok Menipis</flux:select.option>
                    <flux:select.option value="out">Habis</flux:select.option>
                </flux:select>
                <flux:text class="hidden whitespace-nowrap text-xs text-slate-500 sm:block">{{ $this->products->count() }} barang</flux:text>
            </div>
        </div>

        @if ($this->products->isEmpty())
            <div class="px-5 py-16 text-center"><flux:icon name="cube" class="mx-auto size-8 text-slate-300" /><flux:heading size="sm" class="mt-3">Data persediaan tidak ditemukan</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Coba ubah kata pencarian atau filter status.</flux:text></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="w-14 px-5 py-3 font-semibold">No</th><th class="px-5 py-3 font-semibold">Kode Barang</th><th class="px-5 py-3 font-semibold">Nama Barang</th><th class="px-5 py-3 font-semibold">Kategori</th><th class="px-5 py-3 font-semibold">Stok Saat Ini</th><th class="px-5 py-3 font-semibold">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->products as $product)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="inventory-product-{{ $product->id }}"><td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td><td class="whitespace-nowrap px-5 py-3.5 font-mono text-[11px] text-slate-500">{{ $product->sku }}</td><td class="px-5 py-3.5 font-semibold text-slate-700">{{ $product->name }}</td><td class="px-5 py-3.5 text-slate-500">{{ $product->category->name }}</td><td class="px-5 py-3.5 font-semibold text-slate-700">{{ $product->stock }}</td><td class="px-5 py-3.5"><flux:badge size="sm" :color="$this->stockStatusColor($product)">{{ $this->stockStatus($product) }}</flux:badge></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-5 py-3 text-xs text-slate-400">Menampilkan {{ $this->products->count() }} dari {{ $this->allProducts->count() }} barang</div>
        @endif
    </div>
</section>
