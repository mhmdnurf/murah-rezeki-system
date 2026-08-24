@php
    $roleLabel = auth()->user()->hasRole(\App\Models\Role::OWNER) ? 'Pemilik' : 'Kasir';

    $salesByDay = [
        ['day' => 'Rab', 'value' => 'Rp2,45 jt', 'height' => '58%'],
        ['day' => 'Kam', 'value' => 'Rp2,98 jt', 'height' => '68%'],
        ['day' => 'Jum', 'value' => 'Rp3,42 jt', 'height' => '78%'],
        ['day' => 'Sab', 'value' => 'Rp3,86 jt', 'height' => '88%'],
        ['day' => 'Min', 'value' => 'Rp2,14 jt', 'height' => '48%'],
        ['day' => 'Sen', 'value' => 'Rp1,54 jt', 'height' => '35%'],
        ['day' => 'Sel', 'value' => 'Rp3,25 jt', 'height' => '74%'],
    ];

    $lowStockItems = [
        ['name' => 'Susu Ultra Milk', 'code' => 'BRG-009', 'status' => 'Sisa 6', 'tone' => 'amber'],
        ['name' => 'Gula Pasir 1 kg', 'code' => 'BRG-004', 'status' => 'Sisa 8', 'tone' => 'amber'],
        ['name' => 'Aqua 600 ml', 'code' => 'BRG-002', 'status' => 'Sisa 18', 'tone' => 'amber'],
        ['name' => 'Rinso 800 gram', 'code' => 'BRG-006', 'status' => 'Habis', 'tone' => 'red'],
        ['name' => 'Beras 5 kg', 'code' => 'BRG-010', 'status' => 'Habis', 'tone' => 'red'],
    ];

    $recentTransactions = [
        ['number' => 'TRX-20260811-008', 'time' => '16:04', 'cashier' => 'Dewi Anggraini', 'items' => '7', 'total' => 'Rp92.500'],
        ['number' => 'TRX-20260811-007', 'time' => '15:41', 'cashier' => 'Dewi Anggraini', 'items' => '3', 'total' => 'Rp41.000'],
        ['number' => 'TRX-20260811-006', 'time' => '15:12', 'cashier' => 'Rahmat Hidayat', 'items' => '12', 'total' => 'Rp218.000'],
        ['number' => 'TRX-20260811-005', 'time' => '14:55', 'cashier' => 'Dewi Anggraini', 'items' => '5', 'total' => 'Rp63.500'],
    ];
@endphp

<x-layouts::app :title="__('Dashboard')">
    <div class="min-h-full space-y-5 bg-slate-50/70 p-1 sm:space-y-6">
        <header class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
            <div>
                <div class="flex items-center gap-2">
                    <flux:heading size="xl" class="tracking-tight text-slate-900">Dashboard</flux:heading>
                    <flux:badge size="sm" color="blue">{{ $roleLabel }}</flux:badge>
                </div>
                <flux:text class="mt-1 text-xs text-slate-500">Pantau aktivitas toko dan persediaan hari ini.</flux:text>
            </div>

            <div class="flex items-center gap-3 text-right">
                <div class="hidden sm:block">
                    <p class="text-xs font-medium text-slate-700">11 Agustus 2026</p>
                    <p class="text-[11px] text-slate-400">Selamat datang kembali</p>
                </div>
                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" color="blue" />
            </div>
        </header>

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4" aria-label="Ringkasan toko">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Penjualan Hari Ini</p><span class="mt-0.5 size-2 rounded-full bg-emerald-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">Rp3.250.000</p>
                <p class="mt-1 text-[11px] font-medium text-emerald-600">+12% dari kemarin</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Transaksi Hari Ini</p><span class="mt-0.5 size-2 rounded-full bg-sky-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">68</p>
                <p class="mt-1 text-[11px] font-medium text-sky-600">+6 transaksi</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Total Barang</p><span class="mt-0.5 size-2 rounded-full bg-slate-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">428</p>
                <p class="mt-1 text-[11px] font-medium text-slate-500">4 kategori</p>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40">
                <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-slate-500">Barang Stok Menipis</p><span class="mt-0.5 size-2 rounded-full bg-amber-500"></span></div>
                <p class="mt-3 text-xl font-bold tracking-tight text-slate-900">12</p>
                <p class="mt-1 text-[11px] font-medium text-amber-600">Perlu segera dipesan</p>
            </div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.55fr)_minmax(300px,0.9fr)]">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40 sm:p-5">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-start">
                    <div><flux:heading size="sm">Penjualan 7 Hari Terakhir</flux:heading><flux:text class="text-[11px] text-slate-400">5 — 11 Agustus 2026</flux:text></div>
                    <p class="text-[11px] text-slate-500">Total <span class="font-semibold text-slate-700">Rp19.640.000</span></p>
                </div>
                <div class="mt-6 grid h-52 grid-cols-7 items-end gap-2 border-b border-slate-100 pb-0 sm:gap-4">
                    @foreach ($salesByDay as $sale)
                        <div class="flex h-full flex-col items-center justify-end gap-2" wire:key="sales-{{ $sale['day'] }}">
                            <span class="text-[9px] font-medium text-slate-500 sm:text-[10px]">{{ $sale['value'] }}</span>
                            <div class="flex h-[78%] w-full items-end"><div class="w-full rounded-t-md {{ $loop->last ? 'bg-sky-500' : 'bg-sky-200' }} transition-colors duration-200 hover:bg-sky-400" style="height: {{ $sale['height'] }}"></div></div>
                            <span class="text-[10px] text-slate-400">{{ $sale['day'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm shadow-slate-200/40 sm:p-5">
                <div class="flex items-center justify-between gap-3"><div><flux:heading size="sm">Stok Menipis</flux:heading><flux:text class="text-[11px] text-slate-400">Perlu perhatian</flux:text></div><flux:badge size="sm" color="amber">12 barang</flux:badge></div>
                <div class="mt-4 divide-y divide-slate-100">
                    @foreach ($lowStockItems as $item)
                        <div class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0" wire:key="stock-{{ $item['code'] }}">
                            <div class="min-w-0"><p class="truncate text-xs font-semibold text-slate-700">{{ $item['name'] }}</p><p class="mt-0.5 text-[10px] text-slate-400">{{ $item['code'] }}</p></div>
                            <flux:badge size="sm" :color="$item['tone']">{{ $item['status'] }}</flux:badge>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
            <div class="flex flex-col justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:px-5"><div><flux:heading size="sm">Transaksi Terbaru</flux:heading><flux:text class="text-[11px] text-slate-400">Aktivitas penjualan terbaru di toko</flux:text></div><flux:button variant="ghost" size="sm" icon-trailing="arrow-right">Lihat Semua</flux:button></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[680px] text-left text-xs">
                    <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400"><tr><th class="px-5 py-3 font-semibold">Nomor Transaksi</th><th class="px-5 py-3 font-semibold">Waktu</th><th class="px-5 py-3 font-semibold">Kasir</th><th class="px-5 py-3 text-center font-semibold">Jumlah Barang</th><th class="px-5 py-3 text-right font-semibold">Total</th></tr></thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($recentTransactions as $transaction)
                            <tr class="transition-colors duration-150 hover:bg-slate-50" wire:key="transaction-{{ $transaction['number'] }}"><td class="whitespace-nowrap px-5 py-3.5 font-semibold text-sky-600">{{ $transaction['number'] }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-500">{{ $transaction['time'] }}</td><td class="whitespace-nowrap px-5 py-3.5 text-slate-600">{{ $transaction['cashier'] }}</td><td class="px-5 py-3.5 text-center text-slate-600">{{ $transaction['items'] }}</td><td class="whitespace-nowrap px-5 py-3.5 text-right font-semibold text-slate-700">{{ $transaction['total'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-layouts::app>
