<?php

use App\Models\Category;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Kategori Barang')] class extends Component
{
    public string $name = '';

    public string $search = '';

    public bool $showForm = false;

    public bool $showDeleteConfirmation = false;

    public ?int $editingCategoryId = null;

    public ?int $deletingCategoryId = null;

    public string $deletingCategoryName = '';

    /**
     * Get the filtered categories ordered alphabetically.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed]
    public function categories(): \Illuminate\Database\Eloquent\Collection
    {
        return Category::query()
            ->withCount('products')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Open the form for a new category.
     */
    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    /**
     * Save a new category or update the selected category.
     */
    public function save(): void
    {
        $validated = $this->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Category::class, 'name')->ignore($this->editingCategoryId),
            ],
        ]);

        if ($this->editingCategoryId !== null) {
            Category::query()->findOrFail($this->editingCategoryId)->update($validated);
            $message = 'Kategori berhasil diperbarui.';
        } else {
            Category::create($validated);
            $message = 'Kategori berhasil ditambahkan.';
        }

        $this->showForm = false;
        $this->resetForm();
        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * Load a category into the form for editing.
     */
    public function edit(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->editingCategoryId = $category->id;
        $this->name = $category->name;
        $this->showForm = true;
        $this->resetValidation();
    }

    /**
     * Delete a category.
     */
    public function confirmDelete(int $categoryId): void
    {
        $category = Category::query()->findOrFail($categoryId);

        $this->deletingCategoryId = $category->id;
        $this->deletingCategoryName = $category->name;
        $this->showDeleteConfirmation = true;
    }

    /**
     * Delete the category after confirmation.
     */
    public function deleteConfirmed(): void
    {
        if ($this->deletingCategoryId === null) {
            return;
        }

        Category::query()->findOrFail($this->deletingCategoryId)->delete();

        if ($this->editingCategoryId === $this->deletingCategoryId) {
            $this->showForm = false;
            $this->resetForm();
        }

        $this->closeDeleteConfirmation();
        Flux::toast(variant: 'success', text: 'Kategori berhasil dihapus.');
    }

    /**
     * Close the delete confirmation modal.
     */
    public function closeDeleteConfirmation(): void
    {
        $this->showDeleteConfirmation = false;
        $this->deletingCategoryId = null;
        $this->deletingCategoryName = '';
    }

    /**
     * Close the category form.
     */
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    /**
     * Reset the category form state.
     */
    private function resetForm(): void
    {
        $this->reset(['name', 'editingCategoryId']);
        $this->resetValidation();
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Kategori Barang</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Master Data / Kategori Barang</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" class="!bg-sky-500 hover:!bg-sky-600" wire:click="create">Tambah Kategori</flux:button>
    </header>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="relative w-full sm:max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari kategori" aria-label="Cari kategori" />
            </div>
            <flux:text class="text-xs text-slate-500">{{ $this->categories->count() }} kategori ditemukan</flux:text>
        </div>

        @if ($this->categories->isEmpty())
            <div class="px-5 py-16 text-center">
                <flux:icon name="tag" class="mx-auto size-8 text-slate-300" />
                <flux:heading size="sm" class="mt-3">Belum ada kategori</flux:heading>
                <flux:text class="mt-1 text-xs text-slate-500">Tambahkan kategori pertama menggunakan tombol di atas.</flux:text>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400">
                        <tr>
                            <th class="w-16 px-5 py-3 font-semibold">No</th>
                            <th class="px-5 py-3 font-semibold">Nama Kategori</th>
                            <th class="px-5 py-3 font-semibold">Jumlah Barang</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($this->categories as $category)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="category-{{ $category->id }}">
                                <td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td>
                                <td class="px-5 py-3.5 font-semibold text-slate-700">{{ $category->name }}</td>
                                <td class="px-5 py-3.5 text-slate-500">{{ $category->products_count }} barang</td>
                                <td class="px-5 py-3.5"><flux:badge size="sm" color="green">Aktif</flux:badge></td>
                                <td class="px-5 py-3.5">
                                    <div class="flex justify-end gap-2">
                                        <flux:button type="button" variant="ghost" size="sm" wire:click="edit({{ $category->id }})">Ubah</flux:button>
                                        <flux:button type="button" variant="ghost" size="sm" class="!text-red-600 hover:!bg-red-50" wire:click="confirmDelete({{ $category->id }})">Hapus</flux:button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-col gap-3 border-t border-slate-200 px-5 py-3 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between">
                <span>Menampilkan 1–{{ $this->categories->count() }} dari {{ $this->categories->count() }} kategori</span>
                <div class="flex items-center gap-1">
                    <flux:button variant="ghost" size="sm" disabled>Sebelumnya</flux:button>
                    <flux:button variant="primary" size="sm" class="!bg-sky-500 hover:!bg-sky-600">1</flux:button>
                    <flux:button variant="ghost" size="sm" disabled>Berikutnya</flux:button>
                </div>
            </div>
        @endif
    </div>

    <flux:modal wire:model="showForm" class="md:w-[420px]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ $editingCategoryId ? 'Ubah Kategori' : 'Tambah Kategori' }}</flux:heading>
                <flux:text class="mt-1 text-sm text-slate-500">Gunakan nama yang mudah dikenali saat mengelola barang.</flux:text>
            </div>

            <form wire:submit="save" class="space-y-5">
                <flux:field>
                    <flux:label>Nama Kategori</flux:label>
                    <flux:input wire:model="name" type="text" placeholder="Contoh: Makanan" autofocus />
                    <flux:error name="name" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="ghost" wire:click="closeForm">Batal</flux:button>
                    <flux:button type="submit" variant="primary" class="!bg-sky-500 hover:!bg-sky-600">Simpan</flux:button>
                </div>
            </form>
        </div>
    </flux:modal>

    <flux:modal wire:model="showDeleteConfirmation" class="md:w-[420px]">
        <div class="space-y-6">
            <div class="flex items-start gap-3">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-600">
                    <flux:icon name="trash" class="size-5" />
                </div>
                <div>
                    <flux:heading size="lg">Hapus Kategori?</flux:heading>
                    <flux:text class="mt-1 text-sm leading-5 text-slate-500">
                        Kategori <span class="font-semibold text-slate-700">{{ $deletingCategoryName }}</span> akan dihapus. Tindakan ini tidak dapat dibatalkan.
                    </flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeDeleteConfirmation">Batal</flux:button>
                <flux:button type="button" variant="primary" class="!bg-red-600 hover:!bg-red-700" wire:click="deleteConfirmed">Hapus Kategori</flux:button>
            </div>
        </div>
    </flux:modal>
</section>
