<x-layouts::auth.split :title="__('Masuk ke Sistem')">
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl" class="tracking-tight">Masuk ke Sistem</flux:heading>
            <flux:text class="text-sm text-slate-500">Silakan masuk menggunakan akun Anda</flux:text>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="username"
                label="Nama Pengguna"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocomplete="username"
                placeholder="Masukkan nama pengguna"
            />

            <div class="relative">
                <flux:input
                    name="password"
                    label="Kata Sandi"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Masukkan kata sandi"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute end-0 top-0 text-xs font-medium text-[#119fd4]" :href="route('password.request')" wire:navigate>
                        Lupa kata sandi?
                    </flux:link>
                @endif
            </div>

            <div class="flex items-center justify-between gap-4">
                <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />
            </div>

            <div class="pt-1">
                <flux:button variant="primary" type="submit" class="w-full !bg-[#119fd4] hover:!bg-[#0b8fca]" data-test="login-button">
                    Masuk
                </flux:button>
            </div>
        </form>

        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-xs leading-5 text-slate-600">
            <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">Akses pengguna</p>
            <p><span class="font-semibold text-slate-700">Owner</span> — Kelola data, persediaan, laporan, dan akun.</p>
            <p><span class="font-semibold text-slate-700">Kasir</span> — Kelola transaksi penjualan dan pembayaran.</p>
        </div>
    </div>
</x-layouts::auth.split>
