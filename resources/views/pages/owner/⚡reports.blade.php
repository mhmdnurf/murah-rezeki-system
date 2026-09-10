<?php

use App\Models\Role;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Laporan Penjualan')] class extends Component
{
    use WithPagination;

    public string $startDate = '';
    public string $endDate = '';
    public string $cashierId = '';
    public string $paymentMethod = '';
    public string $status = 'PAID';

    /** @var array<string, mixed> */
    #[Locked]
    public array $filters = [];

    protected ReportService $reports;

    public function boot(ReportService $reports): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);
        $this->reports = $reports;
    }

    public function mount(): void
    {
        $this->resetFilters();
    }

    public function applyFilters(): void
    {
        $this->filters = $this->validate(ReportService::rules(), ReportService::messages());
        $this->resetPage();
        unset($this->sales, $this->summary, $this->labels);
    }

    public function resetFilters(): void
    {
        $this->filters = $this->reports->defaults();
        $this->fill($this->filters);
        $this->resetValidation();
        $this->resetPage();
        unset($this->sales, $this->summary, $this->labels);
    }

    /** @return LengthAwarePaginator<int, \App\Models\Sale> */
    #[Computed]
    public function sales(): LengthAwarePaginator
    {
        return $this->reports->details($this->filters)->paginate(20);
    }

    /** @return array{transactions: int, revenue: string, items: int} */
    #[Computed]
    public function summary(): array
    {
        return $this->reports->summary($this->filters);
    }

    /** @return array<string, string> */
    #[Computed]
    public function labels(): array
    {
        return $this->reports->labels($this->filters);
    }

    /** @return Collection<int, User> */
    #[Computed]
    public function cashiers(): Collection
    {
        return User::query()->where(function ($query): void {
            $query->whereHas('role', fn ($role) => $role->where('slug', Role::CASHIER))
                ->orWhereIn('id', \App\Models\Sale::query()->select('user_id'));
        })->orderBy('name')->get(['id', 'name']);
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Laporan Penjualan</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Operasional / Laporan Penjualan</flux:text>
        </div>
        <div class="flex gap-2">
            <flux:button :href="route('owner.reports.pdf', $filters)" icon="arrow-down-tray">Unduh PDF</flux:button>
            <flux:button :href="route('owner.reports.excel', $filters)" icon="arrow-down-tray" variant="primary">Unduh Excel</flux:button>
        </div>
    </header>

    <form wire:submit="applyFilters" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <flux:input label="Tanggal mulai" type="date" wire:model="startDate" />
            <flux:input label="Tanggal selesai" type="date" wire:model="endDate" />
            <flux:select label="Kasir" wire:model="cashierId">
                <option value="">Semua kasir</option>
                @foreach ($this->cashiers as $cashier)
                    <option value="{{ $cashier->id }}" wire:key="cashier-{{ $cashier->id }}">{{ $cashier->name }}</option>
                @endforeach
            </flux:select>
            <flux:select label="Metode pembayaran" wire:model="paymentMethod">
                <option value="">Semua metode</option>
                <option value="CASH">Tunai</option>
                <option value="QRIS">QRIS</option>
            </flux:select>
            <flux:select label="Status transaksi" wire:model="status">
                <option value="">Semua status</option>
                <option value="PAID">Lunas</option>
                <option value="DRAFT">Draf</option>
                <option value="CANCELLED">Dibatalkan</option>
            </flux:select>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <flux:button type="submit" variant="primary">Terapkan Filter</flux:button>
            <flux:button type="button" wire:click="resetFilters">Reset</flux:button>
            <span wire:loading class="text-xs text-slate-500">Memuat laporan…</span>
            <flux:text class="text-xs">Terapkan filter sebelum mengunduh laporan.</flux:text>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <flux:text>Total Transaksi</flux:text>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($this->summary['transactions'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <flux:text>Total Penjualan Lunas</flux:text>
            <p class="mt-2 text-2xl font-semibold text-sky-600">Rp{{ number_format((float) $this->summary['revenue'], 0, ',', '.') }}</p>
            <flux:text class="mt-1 text-xs">Hanya transaksi berstatus lunas.</flux:text>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5">
            <flux:text>Jumlah Unit Barang</flux:text>
            <p class="mt-2 text-2xl font-semibold text-slate-900">{{ number_format($this->summary['items'], 0, ',', '.') }}</p>
            <flux:text class="mt-1 text-xs">Sesuai filter transaksi yang diterapkan.</flux:text>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="space-y-2 border-b border-slate-200 p-5">
            <flux:heading size="sm">Detail Penjualan</flux:heading>
            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
                @foreach ($this->labels as $label => $value)
                    <span wire:key="filter-{{ $label }}">{{ $label }}: {{ $value }}</span>
                @endforeach
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[850px] text-left text-xs">
                <thead class="bg-slate-50 uppercase text-slate-500">
                    <tr>
                        @foreach (['Invoice', 'Tanggal', 'Kasir', 'Jumlah Unit', 'Total', 'Pembayaran', 'Status'] as $heading)
                            <th scope="col" class="px-5 py-3" wire:key="heading-{{ $loop->index }}">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->sales as $sale)
                        <tr wire:key="sale-{{ $sale->id }}" class="hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-mono font-semibold text-sky-600">{{ $sale->invoice_number }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5">{{ $sale->transaction_date->format('d/m/Y') }}</td>
                            <td class="px-5 py-3.5">{{ $sale->cashier?->name ?? '—' }}</td>
                            <td class="px-5 py-3.5">{{ (int) $sale->items_sum_quantity }}</td>
                            <td class="whitespace-nowrap px-5 py-3.5 font-semibold">Rp{{ number_format((float) $sale->total, 0, ',', '.') }}</td>
                            <td class="px-5 py-3.5">{{ $sale->payments->pluck('method')->unique()->map(fn ($method) => ReportService::paymentLabel($method))->implode(', ') ?: '—' }}</td>
                            <td class="px-5 py-3.5">{{ ReportService::statusLabel($sale->status) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-14 text-center text-slate-500">Tidak ada transaksi pada filter ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-5">{{ $this->sales->links() }}</div>
    </div>
</section>
