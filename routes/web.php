<?php

use App\Http\Controllers\Owner\ReportController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login')->name('home');

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

    Route::middleware('role:'.Role::OWNER)->group(function () {
        Route::livewire('owner/qris', 'pages::owner.qris')->name('owner.qris');
        Route::livewire('owner/users', 'pages::owner.users')->name('owner.users');
        Route::livewire('owner/reports', 'pages::owner.reports')->name('owner.reports');
        Route::get('owner/reports/pdf', [ReportController::class, 'pdf'])->name('owner.reports.pdf');
        Route::get('owner/reports/excel', [ReportController::class, 'excel'])->name('owner.reports.excel');
    });

    Route::livewire('owner/categories', 'pages::owner.categories')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.categories');

    Route::livewire('owner/products', 'pages::owner.products')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.products');

    Route::livewire('owner/inventory', 'pages::owner.inventory')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.inventory');

    Route::livewire('owner/stock-in', 'pages::owner.stock-in')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.stock-in');

    Route::livewire('owner/stock-out', 'pages::owner.stock-out')
        ->middleware('role:'.Role::OWNER)
        ->name('owner.stock-out');

    Route::view('cashier/dashboard', 'dashboard')
        ->middleware('role:'.Role::CASHIER)
        ->name('cashier.dashboard');

    Route::livewire('cashier/sales', 'pages::cashier.sales')
        ->middleware('role:'.Role::CASHIER)
        ->name('cashier.sales');

    Route::livewire('sales/history', 'pages::sales.history')
        ->middleware('role:'.Role::OWNER.','.Role::CASHIER)
        ->name('sales.history');
});

require __DIR__.'/settings.php';
