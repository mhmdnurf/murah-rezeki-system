<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Barang Masuk')] class extends Component
{
    public string $productId = '';

    public string $quantity = '1';

    public string $note = '';

    public string $search = '';

    public bool $showForm = false;

    /**
     * Get active products available for stock receipt.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    #[Computed]
    public function products(): \Illuminate\Database\Eloquent\Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('sku', 'like', '%'.$this->search.'%')
                        ->orWhere('name', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get recent stock-in movements.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, InventoryMovement>
     */
    #[Computed]
    public function movements(): \Illuminate\Database\Eloquent\Collection
    {
        return InventoryMovement::query()
            ->with(['product', 'creator'])
            ->where('type', InventoryMovement::TYPE_IN)
            ->latest()
            ->limit(50)
            ->get();
    }

    /**
     * Open the stock receipt form.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Add stock and record its movement atomically.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'productId' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($validated): void {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['productId']);
            $product->increment('stock', $validated['quantity']);

            InventoryMovement::create([
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => $validated['quantity'],
                'note' => $validated['note'] ?: null,
                'created_by' => auth()->id(),
            ]);
        });

        $this->showForm = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: 'Barang masuk berhasil dicatat dan stok diperbarui.');
    }

    /**
     * Close the stock receipt form.
     */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Reset the stock receipt form.
     */
    private function resetForm(): void
    {
        $this->reset(['productId', 'quantity', 'note']);
        $this->quantity = '1';
        $this->resetValidation();
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Barang Masuk</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Inventaris / Barang Masuk</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" class="!bg-sky-500 hover:!bg-sky-600" wire:click="create">Catat Barang Masuk</flux:button>
    </header>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div><flux:heading size="sm">Riwayat Barang Masuk</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Menampilkan 50 penerimaan stok terakhir.</flux:text></div>
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari barang" aria-label="Cari barang" class="w-full sm:max-w-xs" />
        </div>

        @if ($this->movements->isEmpty())
            <div class="px-5 py-16 text-center"><flux:icon name="archive-box-arrow-down" class="mx-auto size-8 text-slate-300" /><flux:heading size="sm" class="mt-3">Belum ada barang masuk</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Catat penerimaan stok pertama menggunakan tombol di atas.</flux:text></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[850px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="w-14 px-5 py-3 font-semibold">No</th><th class="px-5 py-3 font-semibold">Waktu</th><th class="px-5 py-3 font-semibold">Kode Barang</th><th class="px-5 py-3 font-semibold">Nama Barang</th><th class="px-5 py-3 font-semibold">Jumlah</th><th class="px-5 py-3 font-semibold">Catatan</th><th class="px-5 py-3 font-semibold">Dicatat Oleh</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->movements as $movement)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="movement-{{ $movement->id }}"><td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $movement->created_at->format('d M Y, H:i') }}</td><td class="whitespace-nowrap px-5 py-3.5 font-mono text-[11px] text-slate-500">{{ $movement->product->sku }}</td><td class="px-5 py-3.5 font-semibold text-slate-700">{{ $movement->product->name }}</td><td class="px-5 py-3.5 font-semibold text-emerald-600">+{{ $movement->quantity }}</td><td class="px-5 py-3.5 text-slate-500">{{ $movement->note ?: '—' }}</td><td class="px-5 py-3.5 text-slate-500">{{ $movement->creator->name }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showForm" class="md:w-[520px]">
        <div class="space-y-6"><div><flux:heading size="lg">Catat Barang Masuk</flux:heading><flux:text class="mt-1 text-sm text-slate-500">Stok barang akan bertambah setelah data disimpan.</flux:text></div>
            <form wire:submit="save" class="space-y-4"><flux:field><flux:label>Barang</flux:label><flux:select wire:model="productId"><flux:select.option value="">Pilih barang</flux:select.option>@foreach ($this->products as $product)<flux:select.option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }} (stok {{ $product->stock }})</flux:select.option>@endforeach</flux:select><flux:error name="productId" /></flux:field><flux:field><flux:label>Jumlah Barang</flux:label><flux:input wire:model="quantity" type="number" min="1" step="1" placeholder="1" /><flux:error name="quantity" /></flux:field><flux:field><flux:label>Catatan <span class="font-normal text-slate-400">(opsional)</span></flux:label><flux:textarea wire:model="note" rows="3" placeholder="Contoh: Restock dari supplier" /><flux:error name="note" /></flux:field><div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="closeForm">Batal</flux:button><flux:button type="submit" variant="primary" class="!bg-sky-500 hover:!bg-sky-600">Simpan</flux:button></div></form>
        </div>
    </flux:modal>
</section>
