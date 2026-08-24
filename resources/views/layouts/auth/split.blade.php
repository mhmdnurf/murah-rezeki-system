@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="min-h-screen bg-white antialiased text-slate-950">
        <div class="grid min-h-dvh lg:grid-cols-2">
            <section class="relative hidden overflow-hidden bg-[#119fd4] px-12 py-10 text-white lg:flex lg:flex-col xl:px-16">
                <div class="absolute inset-0 bg-linear-to-br from-[#18a9df] via-[#1197c9] to-[#0879ad]"></div>

                <a href="{{ route('home') }}" class="relative z-10 flex items-center gap-3" wire:navigate>
                    <span class="flex size-9 items-center justify-center rounded-lg bg-white/20 text-xs font-bold tracking-tight text-white ring-1 ring-white/25">
                        MR
                    </span>
                    <span class="flex flex-col leading-tight">
                        <span class="text-sm font-bold">Toko Murah Rezeki</span>
                        <span class="text-[10px] text-white/75">Tanjungpinang</span>
                    </span>
                </a>

                <div class="relative z-10 mt-auto max-w-xl pb-10 pt-20">
                    <p class="mb-4 text-sm font-medium text-white/75">Sistem operasional toko</p>
                    <h1 class="max-w-md text-4xl font-bold leading-[1.08] tracking-tight xl:text-5xl">
                        Modul Kasir<br>
                        Terintegrasi Sistem<br>
                        Persediaan Barang
                    </h1>
                    <p class="mt-6 max-w-md text-sm leading-6 text-white/80">
                        Catat transaksi penjualan, pantau stok secara langsung, dan susun laporan penjualan dalam satu sistem toko.
                    </p>

                    <div class="mt-10 flex gap-10">
                        <div>
                            <p class="text-xl font-bold">Master</p>
                            <p class="mt-1 text-xs text-white/70">Data barang</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold">POS</p>
                            <p class="mt-1 text-xs text-white/70">Transaksi kasir</p>
                        </div>
                        <div>
                            <p class="text-xl font-bold">2</p>
                            <p class="mt-1 text-xs text-white/70">Peran pengguna</p>
                        </div>
                    </div>
                </div>

                <p class="relative z-10 text-[11px] text-white/65">© {{ now()->year }} Toko Murah Rezeki · Versi 1.0</p>
            </section>

            <main class="flex min-h-dvh items-center justify-center px-6 py-10 sm:px-10 lg:px-12 xl:px-20">
                <div class="w-full max-w-md">
                    <div class="mb-12 flex items-center gap-3 lg:hidden">
                        <span class="flex size-9 items-center justify-center rounded-lg bg-[#119fd4] text-xs font-bold text-white">MR</span>
                        <span class="flex flex-col leading-tight">
                            <span class="text-sm font-bold">Toko Murah Rezeki</span>
                            <span class="text-[10px] text-slate-500">Tanjungpinang</span>
                        </span>
                    </div>

                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
