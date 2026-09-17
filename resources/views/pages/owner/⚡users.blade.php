<?php

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Role;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Kelola Akun')] class extends Component
{
    use PasswordValidationRules, ProfileValidationRules, WithPagination;

    public string $search = '';

    public string $name = '';

    public string $username = '';

    public string $email = '';

    public string $role = Role::CASHIER;

    public string $password = '';

    public string $password_confirmation = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingUserId = null;

    public function boot(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);
    }

    /** @return LengthAwarePaginator<int, User> */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('role')
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $query): void {
                    $query->where('name', 'like', '%'.$this->search.'%')
                        ->orWhere('username', 'like', '%'.$this->search.'%')
                        ->orWhere('email', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $userId): void
    {
        $user = User::query()->with('role')->findOrFail($userId);
        $this->resetForm();
        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->username = $user->username ?? '';
        $this->email = $user->email;
        $this->role = $user->role?->slug ?? Role::CASHIER;
        $this->showForm = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->hasRole(Role::OWNER), 403);

        $user = $this->editingUserId === null
            ? new User
            : User::query()->findOrFail($this->editingUserId);

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', Rule::unique(User::class)->ignore($user->id)],
            'role' => ['required', Rule::in([Role::OWNER, Role::CASHIER])],
            'password' => $user->exists && $this->password === '' ? ['nullable'] : $this->passwordRules(),
        ]);

        if ($user->id === auth()->id() && $validated['role'] !== Role::OWNER) {
            $this->addError('role', 'Anda tidak dapat mengubah role akun sendiri.');

            return;
        }

        $user->fill([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
        ]);
        $user->role()->associate(Role::query()->where('slug', $validated['role'])->firstOrFail());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($this->password !== '') {
            $user->password = $validated['password'];
            $user->remember_token = Str::random(60);
        }

        $user->save();
        $this->closeForm();
        $this->resetPage();
        Flux::toast(variant: 'success', text: 'Akun berhasil disimpan.');
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    private function resetForm(): void
    {
        $this->reset(['editingUserId', 'name', 'username', 'email', 'role', 'password', 'password_confirmation']);
        $this->resetValidation();
    }
};
?>

<section class="space-y-5">
    <header class="flex flex-col justify-between gap-3 border-b border-slate-200 pb-5 sm:flex-row sm:items-center">
        <div>
            <flux:heading size="xl" class="tracking-tight text-slate-900">Kelola Akun</flux:heading>
            <flux:text class="mt-1 text-xs text-slate-500">Pemilik / Kelola Akun</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" class="!bg-sky-500 hover:!bg-sky-600" wire:click="create">Tambah Akun</flux:button>
    </header>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-200/40">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
            <div class="w-full sm:max-w-md">
                <flux:input wire:model.live.debounce.300ms="search" type="search" icon="magnifying-glass" placeholder="Cari nama, username, atau email" aria-label="Cari akun" />
            </div>
            <flux:text class="text-xs text-slate-500">{{ $this->users->total() }} akun ditemukan</flux:text>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[680px] text-left text-xs">
                <thead class="bg-slate-50 text-[10px] uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="px-5 py-3 font-semibold">Username</th>
                        <th class="px-5 py-3 font-semibold">Email</th>
                        <th class="px-5 py-3 font-semibold">Role</th>
                        <th class="px-5 py-3 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($this->users as $user)
                        <tr wire:key="user-{{ $user->id }}" class="transition-colors duration-150 hover:bg-slate-50">
                            <td class="px-5 py-3.5 font-semibold text-slate-700">{{ $user->name }} @if ($user->id === auth()->id())<span class="text-slate-400">(Anda)</span>@endif</td>
                            <td class="px-5 py-3.5 text-slate-500">{{ $user->username ?? '—' }}</td>
                            <td class="px-5 py-3.5 text-slate-500">{{ $user->email }}</td>
                            <td class="px-5 py-3.5"><flux:badge size="sm" :color="$user->hasRole(Role::OWNER) ? 'sky' : 'zinc'">{{ $user->role?->name ?? 'Belum ditentukan' }}</flux:badge></td>
                            <td class="px-5 py-3.5 text-right"><flux:button variant="ghost" size="sm" wire:click="edit({{ $user->id }})">Ubah</flux:button></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">Akun tidak ditemukan.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4">{{ $this->users->links() }}</div>
    </div>

    <flux:modal wire:model="showForm" class="md:w-[480px]" @close="closeForm">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingUserId ? 'Ubah Akun' : 'Tambah Akun' }}</flux:heading>
            <flux:input wire:model="name" label="Nama" autocomplete="name" />
            <flux:input wire:model="username" label="Username" autocomplete="off" />
            <flux:input wire:model="email" label="Email" type="email" autocomplete="email" />
            <flux:select wire:model="role" label="Role" :disabled="$editingUserId === auth()->id()">
                <option value="cashier">Kasir</option>
                <option value="owner">Owner</option>
            </flux:select>
            @if ($editingUserId === auth()->id())
                <flux:text class="text-xs text-slate-500">Role akun Anda tetap Owner.</flux:text>
            @endif
            <flux:input wire:model="password" label="Password" type="password" autocomplete="new-password" />
            @if ($editingUserId)
                <flux:text class="text-xs text-slate-500">Kosongkan password jika tidak ingin menggantinya.</flux:text>
            @endif
            <flux:input wire:model="password_confirmation" label="Konfirmasi Password" type="password" autocomplete="new-password" />
            <div class="flex justify-end gap-2">
                <flux:button type="button" variant="ghost" wire:click="closeForm">Batal</flux:button>
                <flux:button type="submit" variant="primary" class="!bg-sky-500 hover:!bg-sky-600" wire:loading.attr="disabled" wire:target="save">Simpan</flux:button>
            </div>
        </form>
    </flux:modal>
</section>
