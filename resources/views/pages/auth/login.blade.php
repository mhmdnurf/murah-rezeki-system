<x-layouts::auth.split :title="__('Masuk ke Sistem')">
    <div class="flex flex-col gap-8">
        <div class="flex flex-col gap-2">
            <flux:heading size="xl" class="tracking-tight">Masuk ke Sistem</flux:heading>
            <flux:text class="text-sm text-slate-500">Silakan masuk menggunakan akun Anda</flux:text>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input name="username" label="Nama Pengguna" :value="old('username')" type="text" required autofocus
                autocomplete="username" placeholder="Masukkan nama pengguna" />

            <div class="relative">
                <flux:input name="password" label="Kata Sandi" type="password" required autocomplete="current-password"
                    placeholder="Masukkan kata sandi" viewable />
            </div>

            <div class="pt-1">
                <flux:button variant="primary" type="submit" class="w-full !bg-[#119fd4] hover:!bg-[#0b8fca]"
                    data-test="login-button">
                    Masuk
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::auth.split>
