<?php

namespace App\Http\Controllers;

use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private const SALE_STATUSES = [4, 5, 6];

    public function showCard(Request $request)
    {
        $filters = $this->dashboardFilters($request);
        $year = (int) ($filters['year'] ?? now()->year);

        if ($this->hasCustomDateRange($filters)) {
            [$startDate, $endDate] = $this->resolveCustomRange($filters);
            $months = $this->buildMonthSeries($startDate, $endDate, function (Carbon $rangeStart, Carbon $rangeEnd) use ($filters) {
                $stock = $this->stockTotals($filters, $rangeStart, $rangeEnd);

                return [
                    'return' => $stock['return_total'],
                    'in' => $stock['in_total'],
                    'out' => $stock['out_total'],
                    'sale' => $this->saleQuantitySum($filters, $rangeStart, $rangeEnd),
                    'waste' => $stock['waste_total'],
                ];
            });
        } else {
            $startDate = Carbon::create($year, 1, 1)->startOfDay();
            $endDate = Carbon::create($year, 12, 31)->endOfDay();
            $currentMonth = $year === (int) now()->year ? now()->month : 12;
            $months = [];

            for ($month = 1; $month <= $currentMonth; $month++) {
                $rangeStart = Carbon::create($year, $month, 1)->startOfMonth();
                $rangeEnd = $rangeStart->copy()->endOfMonth();
                $stock = $this->stockTotals($filters, $rangeStart, $rangeEnd);

                $months[] = [
                    'name' => $rangeStart->format('M'),
                    'return' => $stock['return_total'],
                    'in' => $stock['in_total'],
                    'out' => $stock['out_total'],
                    'sale' => $this->saleQuantitySum($filters, $rangeStart, $rangeEnd),
                    'waste' => $stock['waste_total'],
                ];
            }
        }

        $stockData = $this->stockTotals($filters, $startDate, $endDate);
        $saleTotal = $this->saleQuantitySum($filters, $startDate, $endDate);

        return response()->json([
            'status' => 200,
            'message' => 'Dashboard data fetched successfully',
            'data' => [
                'stock_return' => $stockData['return_total'],
                'stock_in' => $stockData['in_total'],
                'stock_out' => $stockData['out_total'],
                'stock_sale' => $saleTotal,
                'stock_waste' => $stockData['waste_total'],
                'stock_total' => $stockData['return_total'] + $stockData['in_total'],
                'month' => $months,
            ],
        ]);
    }

    public function showGraphic(Request $request)
    {
        $filters = $this->dashboardFilters($request);

        if ($this->hasCustomDateRange($filters)) {
            [$startDate, $endDate] = $this->resolveCustomRange($filters);
            $months = $this->buildMonthSeries($startDate, $endDate, function (Carbon $rangeStart, Carbon $rangeEnd) use ($filters) {
                $stock = $this->stockTotals($filters, $rangeStart, $rangeEnd, true);

                return [
                    'return' => $stock['return_total'],
                    'in' => $stock['in_total'],
                    'out' => $stock['out_total'],
                    'sale' => $stock['sale_total'],
                    'waste' => $stock['waste_total'],
                ];
            });
        } else {
            $month = (int) ($filters['month'] ?? now()->month);
            $year = (int) ($filters['year'] ?? now()->year);
            $startDate = Carbon::create($year, $month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();
            $months = [];

            for ($currentMonth = 1; $currentMonth <= $month; $currentMonth++) {
                $rangeStart = Carbon::create($year, $currentMonth, 1)->startOfMonth();
                $rangeEnd = $rangeStart->copy()->endOfMonth();
                $stock = $this->stockTotals($filters, $rangeStart, $rangeEnd, true);

                $months[] = [
                    'name' => $rangeStart->format('M'),
                    'return' => $stock['return_total'],
                    'in' => $stock['in_total'],
                    'out' => $stock['out_total'],
                    'sale' => $stock['sale_total'],
                    'waste' => $stock['waste_total'],
                ];
            }
        }

        $stockData = $this->stockTotals($filters, $startDate, $endDate, true);

        return response()->json([
            'message' => 'expense data geted successfully!',
            'status' => 200,
            'data' => [
                'stock_return' => $stockData['return_total'],
                'stock_in' => $stockData['in_total'],
                'stock_out' => $stockData['out_total'],
                'stock_sale' => $stockData['sale_total'],
                'stock_waste' => $stockData['waste_total'],
                'stock_total' => $stockData['return_total'] + $stockData['in_total'],
                'month' => $months,
            ],
        ]);
    }

    public function filterDashboard(Request $request)
    {
        $filters = $this->dashboardFilters($request, true);
        $operation = $filters['operation'];
        [$startDate, $endDate] = $this->resolveFilterDashboardRange($filters);
        $chart = [];

        $chart = $this->buildMonthSeries($startDate, $endDate, function (Carbon $rangeStart, Carbon $rangeEnd) use ($operation, $filters) {
            return [
                'quantity' => $this->operationQuantityTotal($operation, $filters, $rangeStart, $rangeEnd),
                'price' => $this->operationPriceTotal($operation, $filters, $rangeStart, $rangeEnd),
            ];
        });

        return response()->json([
            'message' => 'Operation chart fetched successfully!',
            'status' => 200,
            'data' => [
                'operation' => $operation,
                'start_date' => $startDate?->toDateTimeString(),
                'end_date' => $endDate?->toDateTimeString(),
                'user_id' => $filters['user_id'] ?? null,
                'summary' => [
                    'quantity' => $this->operationQuantityTotal($operation, $filters, $startDate, $endDate),
                    'price' => $this->operationPriceTotal($operation, $filters, $startDate, $endDate),
                ],
                'chart' => $chart,
            ],
        ]);
    }

    private function dashboardFilters(Request $request, bool $includeOperation = false): array
    {
        $rules = [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000',
        ];

        if ($includeOperation) {
            $rules['operation'] = 'required|in:sale,purchase,expense,stock,profit';
        }

        return $request->validate($rules);
    }

    private function hasCustomDateRange(array $filters): bool
    {
        return !empty($filters['start_date']) && !empty($filters['end_date']);
    }

    private function resolveCustomRange(array $filters): array
    {
        return [
            Carbon::parse($filters['start_date'])->startOfDay(),
            Carbon::parse($filters['end_date'])->endOfDay(),
        ];
    }

    private function resolveOptionalRange(array $filters): array
    {
        if ($this->hasCustomDateRange($filters)) {
            return $this->resolveCustomRange($filters);
        }

        return [null, null];
    }

    private function resolveFilterDashboardRange(array $filters): array
    {
        if ($this->hasCustomDateRange($filters)) {
            return $this->resolveCustomRange($filters);
        }

        $now = now();

        return [
            $now->copy()->startOfYear(),
            $now->copy()->endOfMonth(),
        ];
    }

    private function monthRanges(Carbon $start, Carbon $end): array
    {
        $ranges = [];
        $cursor = $start->copy()->startOfMonth();
        $lastMonth = $end->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $rangeStart = $cursor->copy()->startOfMonth();
            $rangeEnd = $cursor->copy()->endOfMonth();
            if ($rangeStart->lt($start)) {
                $rangeStart = $start->copy();
            }
            if ($rangeEnd->gt($end)) {
                $rangeEnd = $end->copy();
            }
            $ranges[] = [$rangeStart, $rangeEnd];
            $cursor->addMonth();
        }

        return $ranges;
    }

    private function buildMonthSeries(Carbon $start, Carbon $end, callable $resolver): array
    {
        $data = [];

        foreach ($this->monthRanges($start, $end) as [$rangeStart, $rangeEnd]) {
            $data[] = array_merge([
                'name' => $rangeStart->format($start->year === $end->year ? 'M' : 'M Y'),
            ], $resolver($rangeStart, $rangeEnd));
        }

        return $data;
    }

    private function stockBaseQuery(array $filters)
    {
        $query = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->where('sd.is_deleted', 0)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('sm.stock_created_by', $filters['user_id']);
        }

        return $query;
    }

    private function saleQuantityBaseQuery(array $filters)
    {
        $query = DB::table('order_items as oi')
            ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->where('oi.is_deleted', 0)
            ->where('om.is_deleted', 0)
            ->whereIn('om.status', self::SALE_STATUSES)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('om.created_by', $filters['user_id']);
        }

        return $query;
    }

    private function saleAmountBaseQuery(array $filters)
    {
        $query = DB::table('order_masters as om')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->where('om.is_deleted', 0)
            ->whereIn('om.status', self::SALE_STATUSES)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('om.created_by', $filters['user_id']);
        }

        return $query;
    }

    private function purchaseQuantityBaseQuery(array $filters)
    {
        $query = DB::table('purchase_details as pd')
            ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->where('pd.is_deleted', 0)
            ->where('p.is_deleted', 0)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('p.created_by', $filters['user_id']);
        }

        return $query;
    }

    private function purchaseAmountBaseQuery(array $filters)
    {
        $query = DB::table('purchases as p')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->where('p.is_deleted', 0)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('p.created_by', $filters['user_id']);
        }

        return $query;
    }

    private function expenseAmountBaseQuery(array $filters)
    {
        $query = DB::table('expense_masters as em')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('em.is_deleted', 0)
            ->where('u.profile_id', Auth::user()->profile_id);

        if (!empty($filters['user_id'])) {
            $query->where('em.created_by', $filters['user_id']);
        }

        return $query;
    }

    private function stockTotals(array $filters, Carbon $start, Carbon $end, bool $includeSaleType = false): array
    {
        $select = [
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END), 0) AS return_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END), 0) AS in_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END), 0) AS out_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END), 0) AS waste_total',
        ];

        if ($includeSaleType) {
            $select[] = 'COALESCE(SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END), 0) AS sale_total';
        }

        $row = $this->stockBaseQuery($filters)
            ->whereBetween('sm.stock_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->selectRaw(implode(', ', $select))
            ->first();

        return [
            'return_total' => (float) ($row->return_total ?? 0),
            'in_total' => (float) ($row->in_total ?? 0),
            'out_total' => (float) ($row->out_total ?? 0),
            'waste_total' => (float) ($row->waste_total ?? 0),
            'sale_total' => (float) ($row->sale_total ?? 0),
        ];
    }

    private function stockTotalsForOptionalRange(array $filters, ?Carbon $start, ?Carbon $end, bool $includeSaleType = false): array
    {
        $select = [
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END), 0) AS return_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END), 0) AS in_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END), 0) AS out_total',
            'COALESCE(SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END), 0) AS waste_total',
        ];

        if ($includeSaleType) {
            $select[] = 'COALESCE(SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END), 0) AS sale_total';
        }

        $query = $this->stockBaseQuery($filters);

        if ($start && $end) {
            $query->whereBetween('sm.stock_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        }

        $row = $query->selectRaw(implode(', ', $select))->first();

        return [
            'return_total' => (float) ($row->return_total ?? 0),
            'in_total' => (float) ($row->in_total ?? 0),
            'out_total' => (float) ($row->out_total ?? 0),
            'waste_total' => (float) ($row->waste_total ?? 0),
            'sale_total' => (float) ($row->sale_total ?? 0),
        ];
    }

    private function saleQuantitySum(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) $this->saleQuantityBaseQuery($filters)
            ->whereBetween('om.order_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('oi.quantity');
    }

    private function saleAmountSum(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) $this->saleAmountBaseQuery($filters)
            ->whereBetween('om.order_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('om.payment');
    }

    private function purchaseQuantitySum(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) $this->purchaseQuantityBaseQuery($filters)
            ->whereBetween('p.purchase_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('pd.quantity');
    }

    private function purchaseAmountSum(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) $this->purchaseAmountBaseQuery($filters)
            ->whereBetween('p.purchase_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('p.total_amount');
    }

    private function expenseAmountSum(array $filters, Carbon $start, Carbon $end): float
    {
        return (float) $this->expenseAmountBaseQuery($filters)
            ->whereBetween('em.expense_date', [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')])
            ->sum('em.amount');
    }

    private function profitAmountSum(array $filters, Carbon $start, Carbon $end): float
    {
        return $this->saleAmountSum($filters, $start, $end)
            - $this->purchaseAmountSum($filters, $start, $end)
            - $this->expenseAmountSum($filters, $start, $end);
    }

    private function operationQuantityTotal(string $operation, array $filters, ?Carbon $start, ?Carbon $end): float
    {
        if ($operation === 'stock') {
            $stock = $this->stockTotalsForOptionalRange($filters, $start, $end, true);

            return $stock['return_total']
                + $stock['in_total']
                + $stock['out_total']
                + $stock['sale_total']
                + $stock['waste_total'];
        }

        return match ($operation) {
            'sale' => $this->sumWithOptionalRange($this->saleQuantityBaseQuery($filters), 'om.order_date', 'oi.quantity', $start, $end),
            'purchase' => $this->sumWithOptionalRange($this->purchaseQuantityBaseQuery($filters), 'p.purchase_date', 'pd.quantity', $start, $end),
            default => 0.0,
        };
    }

    private function operationPriceTotal(string $operation, array $filters, ?Carbon $start, ?Carbon $end): float
    {
        return match ($operation) {
            'sale' => $this->sumWithOptionalRange($this->saleAmountBaseQuery($filters), 'om.order_date', 'om.payment', $start, $end),
            'purchase' => $this->sumWithOptionalRange($this->purchaseAmountBaseQuery($filters), 'p.purchase_date', 'p.total_amount', $start, $end),
            'expense' => $this->sumWithOptionalRange($this->expenseAmountBaseQuery($filters), 'em.expense_date', 'em.amount', $start, $end),
            'profit' => $this->operationPriceTotal('sale', $filters, $start, $end)
                - $this->operationPriceTotal('purchase', $filters, $start, $end)
                - $this->operationPriceTotal('expense', $filters, $start, $end),
            default => 0.0,
        };
    }

    private function sumWithOptionalRange($query, string $dateColumn, string $sumColumn, ?Carbon $start, ?Carbon $end): float
    {
        if ($start && $end) {
            $query->whereBetween($dateColumn, [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')]);
        }

        return (float) $query->sum($sumColumn);
    }
}
