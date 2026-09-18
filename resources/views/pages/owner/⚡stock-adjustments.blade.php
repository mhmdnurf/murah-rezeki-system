<?php

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Penyesuaian Stok')] class extends Component
{
    public string $productId = '';

    public string $actualStock = '';

    public string $note = '';

    public string $search = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $expectedStock = null;

    public function boot(): void
    {
        $this->authorizeOwner();
    }

    /** @return Collection<int, Product> */
    #[Computed]
    public function products(): Collection
    {
        return Product::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'stock']);
    }

    #[Computed]
    public function selectedProduct(): ?Product
    {
        if ($this->productId === '') {
            return null;
        }

        return $this->products->firstWhere('id', (int) $this->productId);
    }

    /** @return Collection<int, InventoryMovement> */
    #[Computed]
    public function movements(): Collection
    {
        return InventoryMovement::query()
            ->with(['product:id,sku,name', 'creator:id,name'])
            ->where('type', InventoryMovement::TYPE_ADJUSTMENT)
            ->when($this->search !== '', function ($query): void {
                $query->whereHas('product', function ($products): void {
                    $products->where(function ($products): void {
                        $products->where('sku', 'like', '%'.$this->search.'%')
                            ->orWhere('name', 'like', '%'.$this->search.'%');
                    });
                });
            })
            ->latest()
            ->limit(50)
            ->get();
    }

    public function create(): void
    {
        $this->authorizeOwner();
        $this->resetForm();
        $this->showForm = true;
    }

    public function updatedProductId(): void
    {
        $this->resetValidation(['productId', 'actualStock']);
        $this->actualStock = '';
        $this->expectedStock = Product::query()
            ->where('is_active', true)
            ->whereKey($this->productId)
            ->value('stock');
        unset($this->selectedProduct);
    }

    public function save(): void
    {
        $this->authorizeOwner();

        $validated = $this->validate([
            'productId' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('is_active', true)],
            'actualStock' => ['required', 'integer', 'min:0'],
            'note' => ['required', 'string', 'min:3', 'max:500'],
        ], [
            'actualStock.required' => 'Stok fisik wajib diisi.',
            'actualStock.integer' => 'Stok fisik harus berupa bilangan bulat.',
            'actualStock.min' => 'Stok fisik tidak boleh negatif.',
            'note.required' => 'Alasan penyesuaian wajib diisi.',
            'note.min' => 'Alasan penyesuaian minimal 3 karakter.',
        ]);

        $result = DB::transaction(function () use ($validated): string {
            $product = Product::query()->lockForUpdate()->findOrFail($validated['productId']);
            $stockBefore = $product->stock;
            $stockAfter = (int) $validated['actualStock'];

            if ($this->expectedStock === null || $stockBefore !== $this->expectedStock) {
                return 'changed';
            }

            if ($stockBefore === $stockAfter) {
                return 'unchanged';
            }

            $product->update(['stock' => $stockAfter]);

            InventoryMovement::create([
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => abs($stockAfter - $stockBefore),
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'note' => $validated['note'],
                'created_by' => auth()->id(),
            ]);

            return 'saved';
        });

        if ($result === 'changed') {
            $this->addError('actualStock', 'Stok telah berubah. Pilih ulang barang dan periksa stok terbaru.');

            return;
        }

        if ($result === 'unchanged') {
            $this->addError('actualStock', 'Stok fisik sama dengan stok yang tercatat.');

            return;
        }

        $this->showForm = false;
        $this->resetForm();
        unset($this->movements, $this->products, $this->selectedProduct);
        Flux::toast(variant: 'success', text: 'Penyesuaian stok berhasil dicatat.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function authorizeOwner(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);
    }

    private function resetForm(): void
    {
        $this->reset(['productId', 'actualStock', 'note', 'expectedStock']);
        $this->resetValidation();
        unset($this->selectedProduct);
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Penyesuaian Stok</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Inventaris / Penyesuaian Stok</flux:text>
        </div>
        <flux:button variant="primary" icon="adjustments-horizontal" class="!bg-sky-500 hover:!bg-sky-600" wire:click="create">Catat Penyesuaian</flux:button>
    </header>

    <flux:callout color="sky" icon="information-circle" heading="Riwayat stok tetap terjaga" text="Masukkan jumlah stok fisik yang sebenarnya. Sistem akan menyimpan stok sebelum dan sesudah koreksi tanpa mengubah atau menghapus riwayat lama." />

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div><flux:heading size="sm">Riwayat Penyesuaian</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Menampilkan 50 penyesuaian stok terakhir.</flux:text></div>
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari barang" aria-label="Cari riwayat penyesuaian" class="w-full sm:max-w-xs" />
        </div>

        @if ($this->movements->isEmpty())
            <div class="px-5 py-16 text-center"><flux:icon name="adjustments-horizontal" class="mx-auto size-8 text-slate-300" /><flux:heading size="sm" class="mt-3">Belum ada penyesuaian stok</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Catat koreksi pertama berdasarkan hasil pengecekan stok fisik.</flux:text></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[920px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="w-14 px-5 py-3 font-semibold">No</th><th class="px-5 py-3 font-semibold">Waktu</th><th class="px-5 py-3 font-semibold">Barang</th><th class="px-5 py-3 text-center font-semibold">Sebelum</th><th class="px-5 py-3 text-center font-semibold">Sesudah</th><th class="px-5 py-3 text-center font-semibold">Selisih</th><th class="px-5 py-3 font-semibold">Alasan</th><th class="px-5 py-3 font-semibold">Dicatat Oleh</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->movements as $movement)
                            @php($difference = $movement->stock_after - $movement->stock_before)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="adjustment-{{ $movement->id }}"><td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $movement->created_at->format('d M Y, H:i') }}</td><td class="px-5 py-3.5"><p class="font-semibold text-slate-700">{{ $movement->product->name }}</p><p class="mt-0.5 font-mono text-[10px] text-slate-400">{{ $movement->product->sku }}</p></td><td class="px-5 py-3.5 text-center text-slate-600">{{ $movement->stock_before }}</td><td class="px-5 py-3.5 text-center font-semibold text-slate-700">{{ $movement->stock_after }}</td><td class="px-5 py-3.5 text-center"><flux:badge size="sm" :color="$difference > 0 ? 'green' : 'red'">{{ $difference > 0 ? '+' : '' }}{{ $difference }}</flux:badge></td><td class="max-w-64 px-5 py-3.5 text-slate-500">{{ $movement->note }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $movement->creator->name }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showForm" class="md:w-[520px]">
        <div class="space-y-6">
            <div><flux:heading size="lg">Catat Penyesuaian Stok</flux:heading><flux:text class="mt-1 text-sm text-slate-500">Masukkan hasil penghitungan stok fisik, bukan jumlah selisih.</flux:text></div>
            <form wire:submit="save" class="space-y-4">
                <flux:field><flux:label>Barang</flux:label><flux:select wire:model.live="productId"><flux:select.option value="">Pilih barang</flux:select.option>@foreach ($this->products as $product)<flux:select.option value="{{ $product->id }}" wire:key="adjustment-product-{{ $product->id }}">{{ $product->sku }} — {{ $product->name }} (stok {{ $product->stock }})</flux:select.option>@endforeach</flux:select><flux:error name="productId" /></flux:field>
                @if ($this->selectedProduct)
                    <div class="rounded-lg bg-slate-50 p-4 text-sm"><span class="text-slate-500">Stok tercatat saat ini</span><p class="mt-1 text-lg font-semibold text-slate-900">{{ $this->selectedProduct->stock }}</p></div>
                @endif
                <flux:field><flux:label>Stok Fisik Aktual</flux:label><flux:input wire:model="actualStock" type="number" min="0" step="1" placeholder="0" /><flux:error name="actualStock" /></flux:field>
                <flux:field><flux:label>Alasan Penyesuaian</flux:label><flux:textarea wire:model="note" rows="3" placeholder="Contoh: Hasil stok opname menunjukkan selisih" /><flux:error name="note" /></flux:field>
                <div class="flex justify-end gap-2"><flux:button type="button" variant="ghost" wire:click="closeForm">Batal</flux:button><flux:button type="submit" variant="primary" class="!bg-sky-500 hover:!bg-sky-600" wire:loading.attr="disabled">Simpan Penyesuaian</flux:button></div>
            </form>
        </div>
    </flux:modal>
</section>
