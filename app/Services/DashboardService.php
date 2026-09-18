<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $today = CarbonImmutable::today();
        $yesterday = $today->subDay();
        $weekStart = $today->subDays(6);

        $paidSales = $this->salesFor($user)->where('status', Sale::STATUS_PAID);
        $todaySales = (clone $paidSales)->whereDate('transaction_date', $today);
        $yesterdaySales = (clone $paidSales)->whereDate('transaction_date', $yesterday);

        $weeklyTotals = (clone $paidSales)
            ->selectRaw('transaction_date, SUM(total) as daily_total')
            ->whereBetween('transaction_date', [$weekStart, $today])
            ->groupBy('transaction_date')
            ->get()
            ->mapWithKeys(fn (Sale $sale): array => [
                $sale->transaction_date->toDateString() => (int) $sale->getAttribute('daily_total'),
            ]);

        $weeklySales = collect(range(0, 6))->map(function (int $dayOffset) use ($weekStart, $weeklyTotals): array {
            $date = $weekStart->addDays($dayOffset);

            return [
                'date' => $date,
                'day' => $this->dayLabel($date),
                'total' => (int) ($weeklyTotals[$date->toDateString()] ?? 0),
            ];
        });

        $lowStockQuery = Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', 10);

        return [
            'today' => $today,
            'todayRevenue' => (int) (clone $todaySales)->sum('total'),
            'yesterdayRevenue' => (int) (clone $yesterdaySales)->sum('total'),
            'todayTransactions' => (clone $todaySales)->count(),
            'yesterdayTransactions' => (clone $yesterdaySales)->count(),
            'totalProducts' => Product::query()->where('is_active', true)->count(),
            'totalCategories' => Category::query()->count(),
            'lowStockCount' => (clone $lowStockQuery)->count(),
            'lowStockProducts' => $lowStockQuery->orderBy('stock')->orderBy('name')->limit(5)->get(),
            'weeklySales' => $weeklySales,
            'weeklySalesTotal' => $weeklySales->sum('total'),
            'weeklySalesMax' => max(1, $weeklySales->max('total')),
            'recentTransactions' => (clone $paidSales)
                ->with('cashier:id,name')
                ->withSum('items', 'quantity')
                ->latest('transaction_date')
                ->latest('id')
                ->limit(5)
                ->get(),
        ];
    }

    /** @return Builder<Sale> */
    private function salesFor(User $user): Builder
    {
        return Sale::query()
            ->when(
                ! $user->hasRole(Role::OWNER),
                fn (Builder $query): Builder => $query->where('user_id', $user->id),
            );
    }

    private function dayLabel(CarbonImmutable $date): string
    {
        return match ($date->dayOfWeek) {
            CarbonImmutable::SUNDAY => 'Min',
            CarbonImmutable::MONDAY => 'Sen',
            CarbonImmutable::TUESDAY => 'Sel',
            CarbonImmutable::WEDNESDAY => 'Rab',
            CarbonImmutable::THURSDAY => 'Kam',
            CarbonImmutable::FRIDAY => 'Jum',
            CarbonImmutable::SATURDAY => 'Sab',
            default => '—',
        };
    }
}
