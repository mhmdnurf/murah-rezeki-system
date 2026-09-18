@php
    $roleLabel = auth()->user()->hasRole(\App\Models\Role::OWNER) ? 'Pemilik' : 'Kasir';
    $revenueChange = $yesterdayRevenue > 0 ? (int) round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100) : null;
    $transactionDifference = $todayTransactions - $yesterdayTransactions;
    $barHeightClasses = ['h-0', 'h-[10%]', 'h-[20%]', 'h-[30%]', 'h-[40%]', 'h-[50%]', 'h-[60%]', 'h-[70%]', 'h-[80%]', 'h-[90%]', 'h-full'];
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="min-h-full space-y-5 bg-slate-50/70 p-1 sm:space-y-6">
        <header class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" class="tracking-tight text-slate-900">Dashboard</flux:heading>
                    <flux:badge size="sm" color="blue">{{ $roleLabel }}</flux:badge>
                </div>
                <flux:text class="mt-1 text-xs text-slate-500">{{ auth()->user()->hasRole(\App\Models\Role::OWNER) ? 'Pantau aktivitas toko dan persediaan hari ini.' : 'Pantau aktivitas penjualan dan persediaan hari ini.' }}</flux:text>
            </div>

            <div class="flex items-center gap-3 text-right">
                <div class="hidden sm:block">
                    <p class="text-xs font-medium text-slate-700">{{ $today->locale('id')->translatedFormat('j F Y') }}</p>
                    <p class="text-[11px] text-slate-400">Selamat datang kembali</p>
                </div>
                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" color="blue" />
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan toko">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Penjualan Hari Ini</p><span class="mt-0.5 size-2 rounded-full bg-emerald-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">Rp{{ number_format($todayRevenue, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-medium {{ ($revenueChange ?? 0) >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                    @if ($revenueChange === null)
                        Belum ada penjualan kemarin
                    @else
                        {{ $revenueChange >= 0 ? '+' : '' }}{{ $revenueChange }}% dari kemarin
                    @endif
                </p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Transaksi Hari Ini</p><span class="mt-0.5 size-2 rounded-full bg-sky-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">{{ number_format($todayTransactions, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-medium {{ $transactionDifference >= 0 ? 'text-sky-600' : 'text-red-600' }}">{{ $transactionDifference >= 0 ? '+' : '' }}{{ $transactionDifference }} transaksi dari kemarin</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Total Barang</p><span class="mt-0.5 size-2 rounded-full bg-slate-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">{{ number_format($totalProducts, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500">{{ number_format($totalCategories, 0, ',', '.') }} kategori</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Barang Stok Menipis</p><span class="mt-0.5 size-2 rounded-full bg-amber-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">{{ number_format($lowStockCount, 0, ',', '.') }}</p>
                <p class="mt-1 text-[11px] font-medium text-amber-600">Perlu segera dipesan</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.9fr)]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40 sm:p-5">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <div><flux:heading size="sm">Penjualan 7 Hari Terakhir</flux:heading><flux:text class="text-[11px] text-slate-400">{{ $today->subDays(6)->locale('id')->translatedFormat('j M') }} — {{ $today->locale('id')->translatedFormat('j M Y') }}</flux:text></div>
                    <p class="text-[11px] text-slate-500">Total <span class="font-semibold text-slate-700">Rp{{ number_format($weeklySalesTotal, 0, ',', '.') }}</span></p>
                </div>
                <div class="mt-6 grid h-52 grid-cols-7 items-end gap-2 border-b border-slate-100 pb-0 sm:gap-4">
                    @foreach ($weeklySales as $sale)
                        @php($heightClass = $barHeightClasses[(int) ceil(($sale['total'] / $weeklySalesMax) * 10)])
                        <div class="flex h-full flex-col items-center justify-end gap-2" wire:key="sales-{{ $sale['date']->toDateString() }}">
                            <span class="text-[9px] font-medium text-slate-500 sm:text-[10px]">Rp{{ number_format($sale['total'], 0, ',', '.') }}</span>
                            <div class="flex h-[78%] w-full items-end"><div class="w-full rounded-t-md {{ $loop->last ? 'bg-sky-500' : 'bg-sky-200' }} {{ $heightClass }} transition-colors duration-200 hover:bg-sky-400"></div></div>
                            <span class="text-[10px] text-slate-400">{{ $sale['day'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40 sm:p-5">
                <div class="flex items-center justify-between gap-3"><div><flux:heading size="sm">Stok Menipis</flux:heading><flux:text class="text-[11px] text-slate-400">Perlu perhatian</flux:text></div><flux:badge size="sm" color="amber">{{ $lowStockCount }} barang</flux:badge></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @forelse ($lowStockProducts as $product)
                        <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0" wire:key="stock-{{ $product->id }}">
                            <div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-700">{{ $product->name }}</p><p class="mt-0.5 text-[10px] text-slate-400">{{ $product->sku }}</p></div>
                            <flux:badge size="sm" :color="$product->stock === 0 ? 'red' : 'amber'">{{ $product->stock === 0 ? 'Habis' : 'Sisa '.$product->stock }}</flux:badge>
                        </div>
                    @empty
                        <flux:text class="py-8 text-center text-xs text-slate-400">Semua stok masih aman.</flux:text>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:px-5"><div><flux:heading size="sm">Transaksi Terbaru</flux:heading><flux:text class="text-[11px] text-slate-400">Aktivitas penjualan terbaru di toko</flux:text></div><flux:button :href="route('sales.history')" variant="ghost" size="sm" icon-trailing="arrow-right" wire:navigate>Lihat Semua</flux:button></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3 font-semibold">Nomor Transaksi</th><th class="px-5 py-3 font-semibold">Waktu</th><th class="px-5 py-3 font-semibold">Kasir</th><th class="px-5 py-3 text-center font-semibold">Jumlah Barang</th><th class="px-5 py-3 text-right font-semibold">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($recentTransactions as $transaction)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="transaction-{{ $transaction->id }}"><td class="whitespace-nowrap px-5 py-3.5 font-semibold text-sky-600">{{ $transaction->invoice_number }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $transaction->created_at->format('H:i') }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ $transaction->cashier->name }}</td><td class="px-5 py-3.5 text-center text-slate-600">{{ (int) $transaction->items_sum_quantity }}</td><td class="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-slate-700">Rp{{ number_format((float) $transaction->total, 0, ',', '.') }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-slate-400">Belum ada transaksi penjualan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
