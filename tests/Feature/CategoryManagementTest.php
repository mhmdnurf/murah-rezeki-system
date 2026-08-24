<?php

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

function ownerUser(): User
{
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    return $owner;
}

test('only owners can access category management', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)
        ->get(route('owner.categories'))
        ->assertForbidden();

    $this->actingAs(ownerUser())
        ->get(route('owner.categories'))
        ->assertSuccessful();
});

test('owner can create a category', function () {
    $this->actingAs(ownerUser());

    Livewire::test('pages::owner.categories')
        ->set('name', 'Makanan')
        ->call('save')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Makanan')->exists())->toBeTrue();
});

test('category names must be unique', function () {
    $this->actingAs(ownerUser());
    Category::create(['name' => 'Minuman']);

    Livewire::test('pages::owner.categories')
        ->set('name', 'Minuman')
        ->call('save')
        ->assertHasErrors(['name']);
});

test('owner can edit and delete a category', function () {
    $this->actingAs(ownerUser());
    $category = Category::create(['name' => 'Perawatan']);

    Livewire::test('pages::owner.categories')
        ->call('edit', $category->id)
        ->set('name', 'Perawatan Rumah')
        ->call('save')
        ->call('confirmDelete', $category->id)
        ->call('deleteConfirmed')
        ->assertHasNoErrors();

    expect(Category::where('name', 'Perawatan Rumah')->exists())->toBeFalse();
});
