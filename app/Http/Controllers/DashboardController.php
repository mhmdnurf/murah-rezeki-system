<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasRole(Role::OWNER)) {
            return redirect()->route('owner.dashboard');
        }

        if ($user->hasRole(Role::CASHIER)) {
            return redirect()->route('cashier.dashboard');
        }

        return view('dashboard', $this->dashboard->data($user));
    }
}
