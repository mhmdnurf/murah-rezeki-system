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

                    <div class="mt-5 px-2">
                        <details open class="group">
                            <summary class="flex cursor-pointer list-none items-center gap-3 rounded-lg px-2 py-2 text-sm font-medium text-slate-800 transition-colors duration-150 hover:bg-slate-50 [&::-webkit-details-marker]:hidden">
                                <flux:icon name="cube" class="size-4 text-slate-600" />
                                <span>Inventaris</span>
                                <flux:icon name="chevron-down" class="ms-auto size-4 text-slate-500 transition-transform duration-200 group-open:rotate-180" />
                            </summary>

                            <div class="ms-2 mt-1 border-s border-slate-200 ps-5">
                                <a href="{{ route('owner.products') }}" class="block rounded-md px-3 py-2.5 text-sm transition-colors duration-150 {{ request()->routeIs('owner.products') ? 'bg-sky-500 font-medium text-white hover:bg-sky-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" wire:navigate>Data Barang</a>
                                <a href="{{ route('owner.categories') }}" class="block rounded-md px-3 py-2.5 text-sm transition-colors duration-150 {{ request()->routeIs('owner.categories') ? 'bg-sky-500 font-medium text-white hover:bg-sky-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" wire:navigate>Kategori Barang</a>
                                <a href="{{ route('owner.inventory') }}" class="block rounded-md px-3 py-2.5 text-sm transition-colors duration-150 {{ request()->routeIs('owner.inventory') ? 'bg-sky-500 font-medium text-white hover:bg-sky-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" wire:navigate>Persediaan Barang</a>
                                <a href="{{ route('owner.stock-in') }}" class="block rounded-md px-3 py-2.5 text-sm transition-colors duration-150 {{ request()->routeIs('owner.stock-in') ? 'bg-sky-500 font-medium text-white hover:bg-sky-600' : 'text-slate-600 hover:bg-slate-50 hover:text-slate-900' }}" wire:navigate>Barang Masuk</a>
                                <a href="#" class="block rounded-md px-3 py-2.5 text-sm text-slate-600 transition-colors duration-150 hover:bg-slate-50 hover:text-slate-900">Barang Keluar</a>
                            </div>
                        </details>
                    </div>

                    <flux:sidebar.group heading="Operasional" class="mt-5 grid gap-1">
                        <flux:sidebar.item icon="shopping-cart" href="#">Transaksi Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="chart-bar" href="#">Laporan Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="users" href="#">Kelola Akun</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group heading="Menu Kasir" class="grid gap-1">
                        <flux:sidebar.item icon="layout-grid" :href="route('cashier.dashboard')" :current="request()->routeIs('cashier.dashboard', 'dashboard')" wire:navigate>Dashboard</flux:sidebar.item>
                        <flux:sidebar.item icon="shopping-cart" href="#">Transaksi Penjualan</flux:sidebar.item>
                        <flux:sidebar.item icon="clock" href="#">Riwayat Transaksi</flux:sidebar.item>
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
