<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function __invoke(Request $request, Sale $sale): Response
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole(Role::OWNER) || $sale->user_id === $user->id,
            403,
        );

        $sale->load(['cashier', 'items.product', 'payments']);

        return response()
            ->view('sales.receipt', [
                'sale' => $sale,
                'payment' => $sale->payments->first(),
                'backRoute' => $user->hasRole(Role::OWNER) ? 'sales.history' : 'cashier.sales',
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
