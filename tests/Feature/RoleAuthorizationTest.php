<?php

use App\Models\Role;
use App\Models\User;

beforeEach(function () {
    Role::create(['name' => 'Owner', 'slug' => Role::OWNER]);
    Role::create(['name' => 'Cashier', 'slug' => Role::CASHIER]);
});

test('owner can access the owner dashboard', function () {
    $owner = User::factory()->create();
    $owner->role()->associate(Role::where('slug', Role::OWNER)->firstOrFail())->save();

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertSuccessful();
});

test('cashier cannot access the owner dashboard', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)
        ->get(route('owner.dashboard'))
        ->assertForbidden();
});

test('users are redirected to their role dashboard', function () {
    $cashier = User::factory()->create();
    $cashier->role()->associate(Role::where('slug', Role::CASHIER)->firstOrFail())->save();

    $this->actingAs($cashier)
        ->get(route('dashboard'))
        ->assertRedirect(route('cashier.dashboard', absolute: false));
});
