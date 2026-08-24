<?php

use App\Models\Role;
use App\Models\Sale;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Riwayat Transaksi')] class extends Component
{
    public string $search = '';

    public bool $showDetail = false;

    public ?int $selectedSaleId = null;

    /**
     * Get transactions visible to the current user.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Sale>
     */
    #[Computed]
    public function sales(): \Illuminate\Database\Eloquent\Collection
    {
        return Sale::query()
            ->with('cashier')
            ->when(auth()->user()->hasRole(Role::CASHIER), fn ($query) => $query->whereBelongsTo(auth()->user(), 'cashier'))
            ->when($this->search !== '', function ($query): void {
                $query->where(function ($query): void {
                    $query->where('invoice_number', 'like', '%'.$this->search.'%')
                        ->orWhereHas('cashier', fn ($cashierQuery) => $cashierQuery->where('name', 'like', '%'.$this->search.'%'));
                });
            })
            ->latest()
            ->limit(100)
            ->get();
    }

    /**
     * Get the selected transaction with its full detail.
     */
    #[Computed]
    public function selectedSale(): ?Sale
    {
        if ($this->selectedSaleId === null) {
            return null;
        }

        return Sale::query()
            ->with(['items.product', 'payments', 'cashier'])
            ->when(auth()->user()->hasRole(Role::CASHIER), fn ($query) => $query->whereBelongsTo(auth()->user(), 'cashier'))
            ->find($this->selectedSaleId);
    }

    /**
     * Open the transaction detail modal.
     */
    public function viewDetails(int $saleId): void
    {
        $sale = Sale::query()
            ->when(auth()->user()->hasRole(Role::CASHIER), fn ($query) => $query->whereBelongsTo(auth()->user(), 'cashier'))
            ->findOrFail($saleId);

        $this->selectedSaleId = $sale->id;
        $this->showDetail = true;
    }

    /**
     * Close the transaction detail modal.
     */
    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->selectedSaleId = null;
        unset($this->selectedSale);
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center"><div><flux:heading size="xl" class="tracking-tight text-slate-900">Riwayat Transaksi</flux:heading><flux:text class="mt-1 text-xs text-slate-500">{{ auth()->user()->hasRole(\App\Models\Role::OWNER) ? 'Pemilik' : 'Kasir' }} / Riwayat Transaksi</flux:text></div></header>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40"><div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><flux:heading size="sm">Daftar Transaksi</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Menampilkan maksimal 100 transaksi terbaru.</flux:text></div><flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari nomor invoice atau kasir" aria-label="Cari transaksi" class="w-full sm:max-w-xs" /></div>
        @if ($this->sales->isEmpty())
            <div class="px-5 py-16 text-center"><flux:icon name="receipt-percent" class="mx-auto size-8 text-slate-300" /><flux:heading size="sm" class="mt-3">Belum ada transaksi</flux:heading><flux:text class="mt-1 text-xs text-slate-500">Transaksi yang berhasil dibayar akan muncul di sini.</flux:text></div>
        @else
            <div class="overflow-x-auto"><table class="w-full min-w-[820px] text-left text-xs"><thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="w-14 px-5 py-3 font-semibold">No</th><th class="px-5 py-3 font-semibold">Nomor Invoice</th><th class="px-5 py-3 font-semibold">Waktu</th><th class="px-5 py-3 font-semibold">Kasir</th><th class="px-5 py-3 font-semibold">Total</th><th class="px-5 py-3 font-semibold">Status</th><th class="px-5 py-3 text-right font-semibold">Aksi</th></tr></thead><tbody class="divide-y divide-slate-100">@foreach ($this->sales as $sale)<tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="sale-{{ $sale->id }}"><td class="px-5 py-3.5 text-slate-400">{{ $loop->iteration }}</td><td class="whitespace-nowrap px-5 py-3.5 font-mono text-[11px] font-semibold text-sky-600">{{ $sale->invoice_number }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $sale->created_at->format('d M Y, H:i') }}</td><td class="px-5 py-3.5 text-slate-500">{{ $sale->cashier->name }}</td><td class="whitespace-nowrap px-5 py-3.5 font-semibold text-slate-700">Rp{{ number_format((int) $sale->total, 0, ',', '.') }}</td><td class="px-5 py-3.5"><flux:badge size="sm" color="green">{{ $sale->status === \App\Models\Sale::STATUS_PAID ? 'Lunas' : $sale->status }}</flux:badge></td><td class="px-5 py-3.5 text-right"><flux:button type="button" variant="ghost" size="sm" wire:click="viewDetails({{ $sale->id }})">Lihat Detail</flux:button></td></tr>@endforeach</tbody></table></div>
        @endif
    </div>

    <flux:modal wire:model="showDetail" class="md:w-[620px]"><div class="space-y-6">@if ($this->selectedSale)<div class="flex items-start justify-between gap-3"><div><flux:heading size="lg">Detail Transaksi</flux:heading><flux:text class="mt-1 font-mono text-xs text-sky-600">{{ $this->selectedSale->invoice_number }}</flux:text></div><flux:badge color="green">Lunas</flux:badge></div><div class="grid gap-3 rounded-lg bg-slate-50 p-4 text-xs sm:grid-cols-3"><div><span class="text-slate-400">Waktu</span><p class="mt-1 font-medium text-slate-700">{{ $this->selectedSale->created_at->format('d M Y, H:i') }}</p></div><div><span class="text-slate-400">Kasir</span><p class="mt-1 font-medium text-slate-700">{{ $this->selectedSale->cashier->name }}</p></div><div><span class="text-slate-400">Pembayaran</span><p class="mt-1 font-medium text-slate-700">Tunai</p></div></div><div class="divide-y divide-slate-100 rounded-lg border border-slate-200">@foreach ($this->selectedSale->items as $item)<div class="flex items-center justify-between gap-3 px-4 py-3 text-xs"><div><p class="font-semibold text-slate-700">{{ $item->product->name }}</p><p class="mt-1 text-slate-400">{{ $item->quantity }} × Rp{{ number_format((int) $item->unit_price, 0, ',', '.') }}</p></div><span class="font-semibold text-slate-700">Rp{{ number_format((int) $item->subtotal, 0, ',', '.') }}</span></div>@endforeach</div>@php($payment = $this->selectedSale->payments->first())<div class="space-y-2 border-t border-slate-200 pt-4 text-sm"><div class="flex justify-between"><span class="text-slate-500">Subtotal</span><span class="font-medium text-slate-700">Rp{{ number_format((int) $this->selectedSale->subtotal, 0, ',', '.') }}</span></div><div class="flex justify-between font-semibold text-slate-900"><span>Total</span><span>Rp{{ number_format((int) $this->selectedSale->total, 0, ',', '.') }}</span></div><div class="flex justify-between text-xs"><span class="text-slate-500">Uang diterima</span><span class="text-slate-700">Rp{{ number_format((int) $payment->received_amount, 0, ',', '.') }}</span></div><div class="flex justify-between text-xs"><span class="text-slate-500">Kembalian</span><span class="font-semibold text-emerald-600">Rp{{ number_format((int) $payment->change_amount, 0, ',', '.') }}</span></div></div><div class="flex justify-end"><flux:button type="button" variant="ghost" wire:click="closeDetail">Tutup</flux:button></div>@endif</div></flux:modal>
</section>
