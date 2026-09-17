<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function managedAccount(string $role = Role::OWNER): User
{
    $user = User::factory()->create();
    $user->role()->associate(Role::query()->where('slug', $role)->firstOrFail())->save();

    return $user;
}

test('only owners can access account management', function () {
    $this->get(route('owner.users'))->assertRedirect(route('login'));
    $this->actingAs(managedAccount(Role::CASHIER))->get(route('owner.users'))->assertForbidden();
    $this->actingAs(managedAccount())->get(route('owner.users'))->assertSuccessful()->assertSee('Tambah Akun');
});

test('cashiers cannot mount the account component directly', function () {
    $this->actingAs(managedAccount(Role::CASHIER));
    Livewire::test('pages::owner.users')->assertForbidden();
});

test('owner can create accounts with hashed passwords', function (string $role) {
    $this->actingAs(managedAccount());

    Livewire::test('pages::owner.users')
        ->call('create')
        ->set('name', 'Akun Baru')
        ->set('username', 'akun_baru')
        ->set('email', 'baru@example.com')
        ->set('role', $role)
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'SecurePassword123!')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '');

    $user = User::query()->where('username', 'akun_baru')->firstOrFail();
    expect($user->hasRole($role))->toBeTrue()
        ->and(Hash::check('SecurePassword123!', $user->password))->toBeTrue();
})->with([Role::OWNER, Role::CASHIER]);

test('owner can edit an account and role without replacing its password', function () {
    $this->actingAs(managedAccount());
    $user = managedAccount(Role::CASHIER);
    $originalPassword = $user->password;

    Livewire::test('pages::owner.users')
        ->call('edit', $user->id)
        ->assertSet('password', '')
        ->set('name', 'Nama Diperbarui')
        ->set('username', 'nama_baru')
        ->set('email', 'updated@example.com')
        ->set('role', Role::OWNER)
        ->call('save')
        ->assertHasNoErrors();

    expect($user->refresh()->name)->toBe('Nama Diperbarui')
        ->and($user->username)->toBe('nama_baru')
        ->and($user->email)->toBe('updated@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole(Role::OWNER))->toBeTrue()
        ->and($user->password)->toBe($originalPassword);
});

test('owner can replace a password and rotate the remember token', function () {
    $this->actingAs(managedAccount());
    $user = managedAccount(Role::CASHIER);
    $rememberToken = $user->remember_token;

    Livewire::test('pages::owner.users')
        ->call('edit', $user->id)
        ->set('username', 'kasir_password')
        ->set('password', 'NewSecurePassword123!')
        ->set('password_confirmation', 'NewSecurePassword123!')
        ->call('save')
        ->assertHasNoErrors();

    expect(Hash::check('NewSecurePassword123!', $user->refresh()->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($rememberToken);
});

test('account validation rejects duplicates invalid roles and unconfirmed passwords', function () {
    $owner = managedAccount();
    $owner->update(['username' => 'existing_owner']);
    $this->actingAs($owner);

    Livewire::test('pages::owner.users')
        ->set('name', 'Duplicate')
        ->set('username', $owner->username)
        ->set('email', $owner->email)
        ->set('role', 'admin')
        ->set('password', 'SecurePassword123!')
        ->set('password_confirmation', 'different')
        ->call('save')
        ->assertHasErrors(['username' => 'unique', 'email' => 'unique', 'role' => 'in', 'password' => 'confirmed']);

    expect(User::query()->count())->toBe(1);
});

test('new accounts require a password and valid profile fields', function () {
    $this->actingAs(managedAccount());

    Livewire::test('pages::owner.users')
        ->set('username', 'invalid username')
        ->set('email', 'invalid')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'username' => 'alpha_dash', 'email' => 'email', 'password' => 'required']);
});

test('owners cannot demote their own account', function () {
    $owner = managedAccount();
    $owner->update(['username' => 'owner']);
    $this->actingAs($owner);

    Livewire::test('pages::owner.users')
        ->call('edit', $owner->id)
        ->set('role', Role::CASHIER)
        ->call('save')
        ->assertHasErrors(['role']);

    expect($owner->refresh()->hasRole(Role::OWNER))->toBeTrue();
});

test('account editing ids cannot be tampered with', function () {
    $this->actingAs(managedAccount());
    Livewire::test('pages::owner.users')->set('editingUserId', 999);
})->throws(CannotUpdateLockedPropertyException::class);

test('account search matches names usernames and emails', function (string $search) {
    $this->actingAs(managedAccount());
    $user = managedAccount(Role::CASHIER);
    $user->update(['name' => 'Kasir Melati', 'username' => 'melati_store', 'email' => 'melati@example.com']);

    Livewire::test('pages::owner.users')
        ->set('search', $search)
        ->assertSee('Kasir Melati')
        ->assertSee('1 akun ditemukan')
        ->set('search', 'tidak-ada-akun-ini')
        ->assertSee('Akun tidak ditemukan.');
})->with(['Kasir Melati', 'melati_store', 'melati@example.com']);

test('search resets account pagination', function () {
    $this->actingAs(managedAccount());
    User::factory()->count(16)->create();
    User::factory()->create(['name' => 'Target Pagination']);

    Livewire::test('pages::owner.users')
        ->call('setPage', 2)
        ->assertSet('paginators.page', 2)
        ->set('search', 'Target Pagination')
        ->assertSet('paginators.page', 1)
        ->assertSee('Target Pagination')
        ->assertSee('1 akun ditemukan');
});

test('account actions reject a user whose owner access has been revoked', function () {
    $owner = managedAccount();
    $this->actingAs($owner);
    $component = Livewire::test('pages::owner.users');
    $owner->role()->associate(Role::query()->where('slug', Role::CASHIER)->firstOrFail())->save();

    $component->call('create')->assertForbidden();
});

test('closing and reopening the account form clears passwords and validation', function () {
    $this->actingAs(managedAccount());

    Livewire::test('pages::owner.users')
        ->call('create')
        ->set('password', 'secret')
        ->call('save')
        ->assertHasErrors()
        ->call('closeForm')
        ->call('create')
        ->assertHasNoErrors()
        ->assertSet('password', '')
        ->assertSet('editingUserId', null);
});
