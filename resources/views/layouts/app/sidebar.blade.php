@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-slate-200 bg-white">
            <flux:sidebar.header class="border-b border-slate-100 px-4 py-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5" wire:navigate>
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-500 text-[10px] font-bold text-white">MR</span>
                    <span class="flex min-w-0 flex-col leading-tight"><span class="truncate text-xs font-bold text-slate-900">Toko Murah Rezeki</span><span class="text-[9px] text-slate-400">Tanjungpinang</span></span>
                </a>
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav class="px-3 py-4">
                @if (auth()->user()->hasRole(\App\Models\Role::OWNER))
                    <flux:sidebar.group heading="Menu Pemilik" class="grid gap-1">
                        <flux:sidebar.item icon="layout-grid" :href="route('owner.dashboard')" :current="request()->routeIs('owner.dashboard', 'dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group heading="Inventaris" class="mt-5 grid gap-1">
                        <flux:sidebar.item icon="cube" :href="route('owner.products')" :current="request()->routeIs('owner.products')" wire:navigate>Data Barang</flux:sidebar.item>
                        <flux:sidebar.item icon="tag" :href="route('owner.categories')" :current="request()->routeIs('owner.categories')" wire:navigate>Kategori Barang</flux:sidebar.item>
                        <flux:sidebar.item icon="archive-box" :href="route('owner.inventory')" :current="request()->routeIs('owner.inventory')" wire:navigate>Persediaan Barang</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-down-tray" :href="route('owner.stock-in')" :current="request()->routeIs('owner.stock-in')" wire:navigate>Barang Masuk</flux:sidebar.item>
                        <flux:sidebar.item icon="arrow-up-tray" :href="route('owner.stock-out')" :current="request()->routeIs('owner.stock-out')" wire:navigate>Barang Keluar</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group heading="Operasional" class="mt-5 grid gap-1">
                        <flux:sidebar.item icon="receipt-percent" :href="route('sales.history')" :current="request()->routeIs('sales.history')" wire:navigate>Riwayat Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" :href="route('owner.reports')" :current="request()->routeIs('owner.reports*')" wire:navigate>Laporan Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="users" href="#">Kelola Akun</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group heading="Menu Kasir" class="grid gap-1">
                        <flux:sidebar.item icon="layout-grid" :href="route('cashier.dashboard')" :current="request()->routeIs('cashier.dashboard', 'dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-cart" :href="route('cashier.sales')" :current="request()->routeIs('cashier.sales')" wire:navigate>Transaksi Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('sales.history')" :current="request()->routeIs('sales.history')" wire:navigate>Riwayat Transaksi</flux:sidebar.item>
                    </flux:sidebar.group>
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav class="px-3 pb-3">
                <flux:sidebar.item icon="cog" :href="route('profile.edit')" wire:navigate>Pengaturan Akun</flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden border-t border-slate-100 px-3 py-3 lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <flux:header class="border-b border-slate-200 bg-white lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <flux:spacer />
            <flux:profile :name="auth()->user()->name" :initials="auth()->user()->initials()" />
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
