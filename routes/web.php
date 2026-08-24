<?php

use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $user = request()->user();

        if ($user?->hasRole(Role::OWNER)) {
            return redirect()->route('owner.dashboard');
        }

        if ($user?->hasRole(Role::CASHIER)) {
            return redirect()->route('cashier.dashboard');
        }

        return view('dashboard');
    })->name('dashboard');

    Route::view('owner/dashboard', 'dashboard')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.dashboard');

    Route::livewire('owner/categories', 'pages::owner.categories')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.categories');

    Route::view('cashier/dashboard', 'dashboard')
        ->middleware('role:'.Role::CASHIER)
        ->name('cashier.dashboard');
});

require __DIR__.'/settings.php';
