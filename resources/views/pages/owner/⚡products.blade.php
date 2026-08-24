<?php

use App\Models\Category;
use App\Models\Product;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Data Barang')] class extends Component
{
    public string $search = '';

    public string $categoryFilter = '';

    public string $sku = '';

    public string $name = '';

    public string $categoryId = '';

    public string $sellingPrice = '';

    public string $stock = '0';

    public bool $isActive = true;

    public bool $showForm = false;

    public bool $showDeleteConfirmation = false;

    public ?int $editingProductId = null;

    public ?int $deletingProductId = null;

    public string $deletingProductName = '';

    /**
     * Get categories for the product form and filter.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()->orderBy('name')->get();
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
            ->when($this->categoryFilter !== '', fn ($query) => $query->where('category_id', $this->categoryFilter))
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the form for a new product.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Save a new product or update the selected product.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'sku' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Product::class, 'sku')->ignore($this->editingProductId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'categoryId' => ['required', 'integer', 'exists:categories,id'],
            'sellingPrice' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'integer', 'min:0'],
            'isActive' => ['boolean'],
        ]);

        $productData = [
            'sku' => $validated['sku'],
            'name' => $validated['name'],
            'category_id' => $validated['categoryId'],
            'selling_price' => $validated['sellingPrice'],
            'stock' => $validated['stock'],
            'is_active' => $validated['isActive'],
        ];

        if ($this->editingProductId !== null) {
            Product::query()->findOrFail($this->editingProductId)->update($productData);
            $message = 'Data barang berhasil diperbarui.';
        } else {
            Product::create($productData);
            $message = 'Data barang berhasil ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * Load a product into the form for editing.
     */
    public function edit(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        $this->editingProductId = $product->id;
        $this->sku = $product->sku;
        $this->name = $product->name;
        $this->categoryId = (string) $product->category_id;
        $this->sellingPrice = (string) $product->selling_price;
        $this->stock = (string) $product->stock;
        $this->isActive = $product->is_active;
        $this->showForm = true;
        $this->resetValidation();
    }

    /**
     * Open the delete confirmation modal.
     */
    public function confirmDelete(int $productId): void
    {
        $product = Product::query()->findOrFail($productId);

        $this->deletingProductId = $product->id;
        $this->deletingProductName = $product->name;
        $this->showDeleteConfirmation = true;
    }

    /**
     * Delete the product after confirmation.
     */
    public function deleteConfirmed(): void
    {
        if ($this->deletingProductId === null) {
            return;
        }

        Product::query()->findOrFail($this->deletingProductId)->delete();
        $this->closeDeleteConfirmation();
        Flux::toast(variant: 'success', text: 'Data barang berhasil dihapus.');
    }

    /**
     * Close the product form.
     */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->deletingProductId = null;
        $this->deletingProductName = '';
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

    /**
     * Reset the product form state.
     */
    private function resetForm(): void
    {
        $this->reset(['sku', 'name', 'categoryId', 'sellingPrice', 'stock', 'isActive', 'editingProductId']);
        $this->stock = '0';
        $this->isActive = true;
        $this->resetValidation();
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Data Barang</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Inventaris / Data Barang</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" class="!bg-sky-500 hover:!bg-sky-600" wire:click="create">Tambah Barang</flux:button>
    </header>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari barang" aria-label="Cari barang" class="w-full sm:max-w-md" />
            <div class="flex items-center gap-2">
                <flux:select wire:model.live="categoryFilter" aria-label="Filter kategori" class="min-w-40">
                    <flux:select.option value="">Semua Kategori</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:text class="hidden whitespace-nowrap text-xs text-slate-500 sm:block">{{ $this->products->count() }} barang</flux:text>
            </div>
        </div>

        @if ($this->products->isEmpty())
            <div class="px-5 py-16 text-center">
                <flux:icon name="cube" class="mx-auto size-8 text-slate-300" />
                <flux:heading size="sm" class="mt-3">Belum ada data barang</flux:heading>
                <flux:text class="mt-1 text-xs text-slate-500">Tambahkan barang pertama menggunakan tombol di atas.</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="w-14 px-5 py-3 font-semibold">No</th>
                            <th class="px-5 py-3 font-semibold">Kode Barang</th>
                            <th class="px-5 py-3 font-semibold">Nama Barang</th>
                            <th class="px-5 py-3 font-semibold">Kategori</th>
                            <th class="px-5 py-3 font-semibold">Harga Jual</th>
                            <th class="px-5 py-3 font-semibold">Stok</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->products as $product)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="product-{{ $product->id }}">
                                <td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 font-mono text-[11px] text-slate-500">{{ $product->sku }}</td>
                                <td class="px-5 py-3.5 font-semibold text-slate-700">{{ $product->name }}</td>
                                <td class="px-5 py-3.5 text-slate-500">{{ $product->category->name }}</td>
                                <td class="whitespace-nowrap px-5 py-3.5 text-slate-600">Rp{{ number_format((float) $product->selling_price, 0, ',', '.') }}</td>
                                <td class="px-5 py-3.5 font-semibold text-slate-700">{{ $product->stock }}</td>
                                <td class="px-5 py-3.5"><flux:badge size="sm" :color="$this->stockStatusColor($product)">{{ $this->stockStatus($product) }}</flux:badge></td>
                                <td class="px-5 py-3.5">
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="edit({{ $product->id }})">Ubah</flux:button>
                                        <flux:button type="button" variant="ghost" size="sm" class="!text-red-600 hover:!bg-red-50" wire:click="confirmDelete({{ $product->id }})">Hapus</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-3 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                <span>Menampilkan 1–{{ $this->products->count() }} dari {{ $this->products->count() }} barang</span>
                <div class="flex items-center gap-1">
                    <flux:button variant="ghost" size="sm" disabled>Sebelumnya</flux:button>
                    <flux:button variant="primary" size="sm" class="!bg-sky-500 hover:!bg-sky-600">1</flux:button>
                    <flux:button variant="ghost" size="sm" disabled>Berikutnya</flux:button>
                </div>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showForm" class="md:w-[520px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingProductId ? 'Ubah Data Barang' : 'Tambah Data Barang' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-slate-500">Lengkapi informasi barang dan stok awal.</flux:text>
            </div>

            <form wire:submit="save" class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>Kode Barang</flux:label>
                    <flux:input wire:model="sku" type="text" placeholder="Contoh: BRG-001" />
                    <flux:error name="sku" />
                </flux:field>
                <flux:field>
                    <flux:label>Nama Barang</flux:label>
                    <flux:input wire:model="name" type="text" placeholder="Contoh: Indomie Goreng" />
                    <flux:error name="name" />
                </flux:field>
                <flux:field>
                    <flux:label>Kategori</flux:label>
                    <flux:select wire:model="categoryId">
                        <flux:select.option value="">Pilih kategori</flux:select.option>
                        @foreach ($this->categories as $category)
                            <flux:select.option value="{{ $category->id }}">{{ $category->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="categoryId" />
                </flux:field>
                <flux:field>
                    <flux:label>Harga Jual</flux:label>
                    <flux:input wire:model="sellingPrice" type="number" min="0" step="100" placeholder="0" />
                    <flux:error name="sellingPrice" />
                </flux:field>
                <flux:field>
                    <flux:label>Stok Awal</flux:label>
                    <flux:input wire:model="stock" type="number" min="0" step="1" placeholder="0" />
                    <flux:error name="stock" />
                </flux:field>
                <div class="flex items-end pb-2"><flux:checkbox wire:model="isActive" label="Barang aktif" /></div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <flux:button type="button" variant="ghost" wire:click="closeForm">Batal</flux:button>
                    <flux:button type="submit" variant="primary" class="!bg-sky-500 hover:!bg-sky-600">Simpan</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteConfirmation" class="md:w-[420px]">
        <div class="space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600"><flux:icon name="trash" class="size-5" /></div>
                <div>
                    <flux:heading size="lg">Hapus Data Barang?</flux:heading>
                    <flux:text class="mt-1 text-sm leading-5 text-slate-500">Barang <span class="font-semibold text-slate-700">{{ $deletingProductName }}</span> akan dihapus. Tindakan ini tidak dapat dibatalkan.</flux:text>
                </div>
            </div>
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteConfirmation">Batal</flux:button>
                <flux:button type="button" variant="primary" class="!bg-red-600 hover:!bg-red-700" wire:click="deleteConfirmed">Hapus Barang</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
