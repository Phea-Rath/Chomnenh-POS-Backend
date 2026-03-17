<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function showCard(Request $request)
{
    $user = Auth::user();
    $uid = $user->id;
    $proId = $user->profile_id;
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $now = now();
    $currentYear = (int) ($validate['year'] ?? $now->year);
    $currentMonth = $currentYear === (int) $now->year ? $now->month : 12;

    /* ================= TOTAL STOCK ================= */
    $stockData = DB::table('stock_details as sd')
        ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
        ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->where('sd.is_deleted', 0)
        ->where('p.id', $proId)
        // ->where('sm.stock_created_by', $uid)
        ->whereYear('sm.stock_date', $currentYear)
        ->selectRaw("
            COALESCE(SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity END),0) AS return_total,
            COALESCE(SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity END),0) AS in_total,
            COALESCE(SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity END),0) AS out_total,
            COALESCE(SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity END),0) AS waste_total
        ")
        ->first();

    /* ================= TOTAL SALES (ORDERS) ================= */
    $saleTotal = DB::table('order_items as oi')
        ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
        ->join('users as u', 'om.created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->where('p.id', $proId)
        ->where('oi.is_deleted', 0)
        ->where('om.is_deleted', 0)
        ->whereIn('om.status', [4,5,6])
        ->whereYear('om.order_date', $currentYear)
        ->sum('oi.quantity');

    /* ================= MONTHLY STOCK ================= */
    $monthlyStock = DB::table('stock_details as sd')
        ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
        ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->where('sd.is_deleted', 0)
        ->where('p.id', $proId)
        // ->where('sm.stock_created_by', $uid)
        ->whereYear('sm.stock_date', $currentYear)
        ->selectRaw("
            MONTH(sm.stock_date) AS month,
            SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS return_total,
            SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS in_total,
            SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS out_total,
            SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS waste_total
        ")
        ->groupBy('month')
        ->get();

    /* ================= MONTHLY SALES ================= */
    $monthlySales = DB::table('order_items as oi')
        ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
        ->join('users as u', 'om.created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->where('oi.is_deleted', 0)
        ->where('om.is_deleted', 0)
        ->whereIn('om.status', [4,5,6])
        ->where('p.id', $proId)
        ->whereYear('om.order_date', $currentYear)
        ->selectRaw("
            MONTH(om.order_date) AS month,
            SUM(oi.quantity) AS sale_total
        ")
        ->groupBy('month')
        ->get()
        ->keyBy('month');

    /* ================= BUILD MONTH ARRAY ================= */
    $months = [];
    for ($m = 1; $m <= $currentMonth; $m++) {
        $stock = $monthlyStock->firstWhere('month', $m);
        $sale  = $monthlySales[$m]->sale_total ?? 0;

        $months[] = [
            'name'   => date('M', mktime(0, 0, 0, $m, 1)),
            'return' => ($stock->return_total ?? 0) + 0,
            'in'     => ($stock->in_total ?? 0)  + 0,
            'out'    => ($stock->out_total ?? 0) + 0,
            'sale'   => $sale + 0,
            'waste'  => ($stock->waste_total ?? 0) + 0,
        ];
    }

    return response()->json([
        'status' => 200,
        'message' => 'Dashboard data fetched successfully',
        'data' => [
            'stock_return' => $stockData->return_total + 0,
            'stock_in'     => $stockData->in_total + 0,
            'stock_out'    => $stockData->out_total + 0,
            'stock_sale'   => $saleTotal + 0,
            'stock_waste'  => $stockData->waste_total + 0,
            'stock_total'  => $stockData->return_total + $stockData->in_total,
            'month'        => $months
        ]
    ]);
}


    public function showGraphic(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

        // Validate month/year as integers
        $validate = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000',
        ]);

        $month = $validate['month'] ?? now()->month;
        $year = $validate['year'] ?? now()->year;

        // Build start and end date for selected month
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate   = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

        // --- Totals ---
        $stockData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('p.id', $proId)
            ->whereBetween('sm.stock_date', [$startDate, $endDate])
            ->selectRaw("
            SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS return_total,
            SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS in_total,
            SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS out_total,
            SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS sale_total,
            SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS waste_total
        ")
            ->first();

        if (!$stockData) {
            return response()->json([
                'message' => 'expense data get fail!',
                'status' => 404
            ]);
        }

        // --- Monthly breakdown (from Jan to selected month) ---
        $monthlyData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('p.id', $proId)
            ->whereYear('sm.stock_date', $year)
            ->whereMonth('sm.stock_date', '<=', $month)
            ->selectRaw("
            EXTRACT(MONTH FROM sm.stock_date) as month,
            SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS return_total,
            SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS in_total,
            SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS out_total,
            SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS sale_total,
            SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS waste_total
        ")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Build list from Jan -> selected month
        $months = [];
        for ($m = 1; $m <= $month; $m++) {
            $found = $monthlyData->firstWhere('month', $m);
            $months[] = [
                'name' => date('M', mktime(0, 0, 0, $m, 1)),
                'return' => $found->return_total ?? 0,
                'in' => $found->in_total ?? 0,
                'out' => $found->out_total ?? 0,
                'sale' => $found->sale_total ?? 0,
                'waste' => $found->waste_total ?? 0,
            ];
        }

        return response()->json([
            'message' => 'expense data geted successfully!',
            'status' => 200,
            'data' => [
                'stock_return' => $stockData->return_total ?? 0,
                'stock_in' => $stockData->in_total ?? 0,
                'stock_out' => $stockData->out_total ?? 0,
                'stock_sale' => $stockData->sale_total ?? 0,
                'stock_waste' => $stockData->waste_total ?? 0,
                'stock_total' => ($stockData->return_total ?? 0) + ($stockData->in_total ?? 0),
                'month' => $months
            ]
        ]);
    }

    public function saleByWeek(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $month = $base->month;

    // Current and last month date ranges
    $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
    $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

    $lastMonth = $month == 1 ? 12 : $month - 1;
    $lastMonthYear = $month == 1 ? $year - 1 : $year;

    $lastStartDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->startOfMonth();
    $lastEndDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->endOfMonth();

    // Helper: generate weekly ranges between two dates
    $getWeekRanges = function ($start, $end) {
        $ranges = [];
        $current = $start->copy();
        while ($current <= $end) {
            $weekStart = $current->copy();
            $weekEnd = $current->copy()->addDays(6);
            if ($weekEnd > $end) $weekEnd = $end->copy();
            $ranges[] = [$weekStart, $weekEnd];
            $current = $weekEnd->copy()->addDay();
        }
        return $ranges;
    };

    // Helper: get weekly sums (quantity or price)
    $getWeeklySum = function ($proId, $start, $end, $usePrice = false) use ($getWeekRanges) {
        $weeks = [];
        $weekRanges = $getWeekRanges($start, $end);

        foreach ($weekRanges as [$ws, $we]) {
            $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->join('users as u', 'om.created_by', '=', 'u.id')
                ->where('oi.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('om.order_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')]);

            if ($usePrice) {
                $query->select(DB::raw("SUM(om.payment) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(oi.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }

            $weeks[] = $sum;
        }

        return $weeks;
    };

    // Fetch weekly data
    $salesThisMonth = $getWeeklySum($proId, $startDate, $endDate, false); // Sales quantity
    $salesThisMonthPrice = $getWeeklySum($proId, $startDate, $endDate, true); // Sales price
    $salesLastMonth = $getWeeklySum($proId, $lastStartDate, $lastEndDate, false);
    $salesLastMonthPrice = $getWeeklySum($proId, $lastStartDate, $lastEndDate, true);

    // Build response arrays
    $weekCount = max(
        count($salesThisMonth),
        count($salesLastMonth),
    );

    $sales = [];

    for ($i = 0; $i < $weekCount; $i++) {
        $sales[] = [
            'name' => 'Week ' . ($i + 1),
            'thisMonth' => $salesThisMonth[$i] ?? 0,
            'thisMonthPrice' => $salesThisMonthPrice[$i] ?? 0,
            'lastMonth' => $salesLastMonth[$i] ?? 0,
            'lastMonthPrice' => $salesLastMonthPrice[$i] ?? 0,
        ];
    }

    return response()->json([
        'message' => 'expense data fetched successfully!',
        'status' => 200,
        'data' => $sales,
    ]);
}
    public function purchaseByWeek(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $month = $base->month;

    // Current and last month date ranges
    $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
    $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

    $lastMonth = $month == 1 ? 12 : $month - 1;
    $lastMonthYear = $month == 1 ? $year - 1 : $year;

    $lastStartDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->startOfMonth();
    $lastEndDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->endOfMonth();

    // Helper: generate weekly ranges between two dates
    $getWeekRanges = function ($start, $end) {
        $ranges = [];
        $current = $start->copy();
        while ($current <= $end) {
            $weekStart = $current->copy();
            $weekEnd = $current->copy()->addDays(6);
            if ($weekEnd > $end) $weekEnd = $end->copy();
            $ranges[] = [$weekStart, $weekEnd];
            $current = $weekEnd->copy()->addDay();
        }
        return $ranges;
    };

    // Helper: get weekly sums (quantity or price)
    $getWeeklySum = function ($proId, $start, $end, $usePrice = false) use ($getWeekRanges) {
        $weeks = [];
        $weekRanges = $getWeekRanges($start, $end);

        foreach ($weekRanges as [$ws, $we]) {
            $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->join('users as u', 'p.created_by', '=', 'u.id')
                ->where('pd.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('p.purchase_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')]);

            if ($usePrice) {
                $query->select(DB::raw("SUM(p.total_amount) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(pd.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }

            $weeks[] = $sum;
        }

        return $weeks;
    };

    $stockThisMonth = $getWeeklySum($proId, $startDate, $endDate, false); // Stock quantity
    $stockThisMonthPrice = $getWeeklySum($proId, $startDate, $endDate, true);
    $stockLastMonth = $getWeeklySum($proId, $lastStartDate, $lastEndDate, false);
    $stockLastMonthPrice = $getWeeklySum($proId, $lastStartDate, $lastEndDate, true);

    // Build response arrays
    $weekCount = max(
        count($stockThisMonth),
        count($stockLastMonth)
    );

    $stock = [];

    for ($i = 0; $i < $weekCount; $i++) {
        $stock[] = [
            'name' => 'Week ' . ($i + 1),
            'thisMonth' => $stockThisMonth[$i] ?? 0,
            'thisMonthPrice' => $stockThisMonthPrice[$i] ?? 0,
            'lastMonth' => $stockLastMonth[$i] ?? 0,
            'lastMonthPrice' => $stockLastMonthPrice[$i] ?? 0,
        ];
    }

    return response()->json([
        'message' => 'expense data fetched successfully!',
        'status' => 200,
        'data' => $stock
    ]);
}


    public function saleByMonth(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $now = now();
    $currentYear = (int) ($validate['year'] ?? $now->year);
    $lastYear = $currentYear - 1;
    $currentMonth = $currentYear === (int) $now->year ? $now->month : 12;

    $data = [];

    // Helper function to get monthly total (quantity or price)
    $getMonthlySum = function ($proId, $startDate, $endDate, $usePrice = false) {
         $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->join('users as u', 'om.created_by', '=', 'u.id')
                ->where('oi.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('om.order_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($usePrice) {
                $query->select(DB::raw("SUM(om.payment) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(oi.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }
            return $sum;
    };

    // Loop from January to current month
    for ($month = 1; $month <= $currentMonth; $month++) {
        $startThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->startOfMonth();
        $endThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->endOfMonth();
        $startLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->startOfMonth();
        $endLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->endOfMonth();

        // Get sales and stock data
        $salesThisYearQty = $getMonthlySum($proId, $startThisYear, $endThisYear, false);
        $salesThisYearPrice = $getMonthlySum($proId, $startThisYear, $endThisYear, true);
        $salesLastYearQty = $getMonthlySum($proId, $startLastYear, $endLastYear, false);
        $salesLastYearPrice = $getMonthlySum($proId, $startLastYear, $endLastYear, true);

        $monthName = $startThisYear->format('F');

        $data[] = [
                'name' => $monthName,
                'thisYearQty' => $salesThisYearQty,
                'thisYearPrice' => $salesThisYearPrice,
                'lastYearQty' => $salesLastYearQty,
                'lastYearPrice' => $salesLastYearPrice,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}
    public function purchaseByMonth(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $now = now();
    $currentYear = (int) ($validate['year'] ?? $now->year);
    $lastYear = $currentYear - 1;
    $currentMonth = $currentYear === (int) $now->year ? $now->month : 12;

    $data = [];

    // Helper function to get monthly total (quantity or price)
    $getMonthlySum = function ($proId, $startDate, $endDate, $usePrice = false) {
         $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->join('users as u', 'p.created_by', '=', 'u.id')
                ->where('pd.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('p.purchase_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);

            if ($usePrice) {
                $query->select(DB::raw("SUM(p.total_amount) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(pd.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }
            return $sum;
    };

    // Loop from January to current month
    for ($month = 1; $month <= $currentMonth; $month++) {
        $startThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->startOfMonth();
        $endThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->endOfMonth();
        $startLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->startOfMonth();
        $endLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->endOfMonth();

        // Get sales and stock data

        $stockThisYearQty = $getMonthlySum($proId, $startThisYear, $endThisYear, false);
        $stockThisYearPrice = $getMonthlySum($proId, $startThisYear, $endThisYear, true);
        $stockLastYearQty = $getMonthlySum($proId, $startLastYear, $endLastYear, false);
        $stockLastYearPrice = $getMonthlySum($proId, $startLastYear, $endLastYear, true);

        $monthName = $startThisYear->format('F');

        $data[] = [
                'name' => $monthName,
                'thisYearQty' => $stockThisYearQty,
                'thisYearPrice' => $stockThisYearPrice,
                'lastYearQty' => $stockLastYearQty,
                'lastYearPrice' => $stockLastYearPrice,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}


public function saleByHour(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }

    $today = $base->copy()->startOfDay();
    $yesterday = $today->copy()->subDay();

    // Define time slots - you can adjust these times as needed
    $timeSlots = [
        '07:00 AM' => ['start' => '07:00:00', 'end' => '10:59:59'],
        '11:00 AM' => ['start' => '11:00:00', 'end' => '15:59:59'],
        '04:00 PM' => ['start' => '16:00:00', 'end' => '20:59:59'],
        '09:00 PM' => ['start' => '21:00:00', 'end' => '01:59:59'],  // crosses midnight, handled below
        '02:00 AM' => ['start' => '02:00:00', 'end' => '05:59:59'],
        '06:00 AM' => ['start' => '06:00:00', 'end' => '06:59:59'],
    ];

    // Helper function to get sums by time range and day
    $getHourlySum = function ($proId, $day, $startTime, $endTime, $usePrice = false) {
    $query = DB::table('order_items as oi')
        ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
        ->join('users as u', 'om.created_by', '=', 'u.id')
        ->where('oi.is_deleted', 0)
        ->where('u.profile_id', $proId);

    // Handle time range that might cross midnight
    if ($startTime > $endTime) {
        // Between day start and endTime on next day
                        $query->where(function ($q) use ($day, $startTime) {
                        $q->whereRaw('TIME(om.created_at) BETWEEN ? AND ?', [$startTime, '23:59:59'])
                            ->whereRaw('DATE(om.created_at) = ?', [$day->format('Y-m-d')]);
                })->orWhere(function ($q) use ($day, $endTime) {
                        $q->whereRaw('TIME(om.created_at) BETWEEN ? AND ?', ['00:00:00', $endTime])
                            ->whereRaw('DATE(om.created_at) = ?', [$day->copy()->addDay()->format('Y-m-d')]);
                });
    } else {
          $query->whereRaw('DATE(om.created_at) = ?', [$day->format('Y-m-d')])
              ->whereRaw('TIME(om.created_at) BETWEEN ? AND ?', [$startTime, $endTime]);
    }

    if ($usePrice) {
        $query->select(DB::raw("COALESCE(SUM(om.payment), 0) as total"));
    } else {
        $query->select(DB::raw("COALESCE(SUM(oi.quantity), 0) as total"));
    }

    return $query->value('total');
};


    $sales = [];

    foreach ($timeSlots as $label => $times) {
        $startTime = $times['start'];
        $endTime = $times['end'];

        $sales[] = [
            'name' => $label,
            'today' => $getHourlySum($proId, $today, $startTime, $endTime, false),
            'todayPrice' => $getHourlySum($proId, $today, $startTime, $endTime, true),
            'yesterday' => $getHourlySum($proId, $yesterday, $startTime, $endTime, false),
            'yesterdayPrice' => $getHourlySum($proId, $yesterday, $startTime, $endTime, true),
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $sales,

    ]);
}


public function purchaseByHour(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }

    $today = $base->copy()->startOfDay();
    $yesterday = $today->copy()->subDay();

    // Define time slots - you can adjust these times as needed
    $timeSlots = [
        '07:00 AM' => ['start' => '07:00:00', 'end' => '10:59:59'],
        '11:00 AM' => ['start' => '11:00:00', 'end' => '15:59:59'],
        '04:00 PM' => ['start' => '16:00:00', 'end' => '20:59:59'],
        '09:00 PM' => ['start' => '21:00:00', 'end' => '01:59:59'],  // crosses midnight, handled below
        '02:00 AM' => ['start' => '02:00:00', 'end' => '05:59:59'],
        '06:00 AM' => ['start' => '06:00:00', 'end' => '06:59:59'],
    ];

    // Helper function to get sums by time range and day
    $getHourlySum = function ($proId, $day, $startTime, $endTime, $usePrice = false) {
    $query = DB::table('purchase_details as pd')
        ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
        ->join('users as u', 'p.created_by', '=', 'u.id')
        ->where('pd.is_deleted', 0)
        ->where('u.profile_id', $proId);

    // Handle time range that crosses midnight
        if ($startTime > $endTime) {
        $query->where(function ($q) use ($day, $startTime) {
            $q->whereRaw('TIME(p.created_at) BETWEEN ? AND ?', [$startTime, '23:59:59'])
              ->whereRaw('DATE(p.created_at) = ?', [$day->format('Y-m-d')]);
        })->orWhere(function ($q) use ($day, $endTime) {
            $q->whereRaw('TIME(p.created_at) BETWEEN ? AND ?', ['00:00:00', $endTime])
              ->whereRaw('DATE(p.created_at) = ?', [$day->copy()->addDay()->format('Y-m-d')]);
        });
    } else {
        $query->whereRaw('DATE(p.created_at) = ?', [$day->format('Y-m-d')])
              ->whereRaw('TIME(p.created_at) BETWEEN ? AND ?', [$startTime, $endTime]);
    }

    // Select based on usePrice flag
    if ($usePrice) {
        $query->select(DB::raw("COALESCE(SUM(p.total_amount), 0) as total"));
    } else {
        $query->select(DB::raw("COALESCE(SUM(pd.quantity), 0) as total"));
    }

    return $query->value('total');
};


    $stock = [];

    foreach ($timeSlots as $label => $times) {
        $startTime = $times['start'];
        $endTime = $times['end'];

        $stock[] = [
            'name' => $label,
            'today' => $getHourlySum($proId, $today, $startTime, $endTime, false),
            'todayPrice' => $getHourlySum($proId, $today, $startTime, $endTime, true),
            'yesterday' => $getHourlySum($proId, $yesterday, $startTime, $endTime, false),
            'yesterdayPrice' => $getHourlySum($proId, $yesterday, $startTime, $endTime, true),
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' =>  $stock,

    ]);
}


public function saleByDay(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    // Get current date and current week start/end (Monday to Sunday)
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $today = $base;
    $startOfWeek = $today->copy()->startOfWeek();
    $endOfWeek = $today->copy()->endOfWeek();

    // Previous week start/end
    $startOfLastWeek = $startOfWeek->copy()->subWeek();
    $endOfLastWeek = $endOfWeek->copy()->subWeek();

    // Helper: Get daily sum (quantity or price)
    $getDailySum = function ($proId, $date, $usePrice = false) {
        $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->join('users as u', 'om.created_by', '=', 'u.id')
                ->where('oi.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->where('om.order_date', $date->format('Y-m-d'));

            if ($usePrice) {
                $query->select(DB::raw("SUM(om.payment) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(oi.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }
            return $sum;
    };

    $sales = [];

    for ($i = 0; $i < 7; $i++) {
        $currentDay = $startOfWeek->copy()->addDays($i);
        $lastWeekDay = $startOfLastWeek->copy()->addDays($i);

        // Sales quantity and price for current week and last week
        $salesThisWeekQty = $getDailySum($proId, $currentDay, false);
        $salesThisWeekPrice = $getDailySum($proId, $currentDay, true);
        $salesLastWeekQty = $getDailySum($proId, $lastWeekDay, false);
        $salesLastWeekPrice = $getDailySum($proId, $lastWeekDay, true);

        $sales[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $salesThisWeekQty,
            'thisWeekPrice' => $salesThisWeekPrice,
            'Weekend' => $salesLastWeekQty,
            'WeekendPrice' => $salesLastWeekPrice,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' =>  $sales,
    ]);
}


public function purchaseByDay(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    // Get current date and current week start/end (Monday to Sunday)
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $today = $base;
    $startOfWeek = $today->copy()->startOfWeek();
    $endOfWeek = $today->copy()->endOfWeek();

    // Previous week start/end
    $startOfLastWeek = $startOfWeek->copy()->subWeek();
    $endOfLastWeek = $endOfWeek->copy()->subWeek();

    // Helper: Get daily sum (quantity or price)
    $getDailySum = function ($proId, $date, $usePrice = false) {
        $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->join('users as u', 'p.created_by', '=', 'u.id')
                ->where('pd.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->where('p.purchase_date', $date->format('Y-m-d'));

            if ($usePrice) {
                $query->select(DB::raw("SUM(p.total_amount) as total"));
                $sum = $query->value('total') ?? 0;
            } else {
                $query->select(DB::raw("SUM(pd.quantity) as total"));
                $sum = $query->value('total') ?? 0;
            }
            return $sum;
    };

    $stock = [];

    for ($i = 0; $i < 7; $i++) {
        $currentDay = $startOfWeek->copy()->addDays($i);
        $lastWeekDay = $startOfLastWeek->copy()->addDays($i);

        // Stock quantity and price for current week and last week
        $stockThisWeekQty = $getDailySum($proId, $currentDay, false);
        $stockThisWeekPrice = $getDailySum($proId, $currentDay, true);
        $stockLastWeekQty = $getDailySum($proId, $lastWeekDay, false);
        $stockLastWeekPrice = $getDailySum($proId, $lastWeekDay, true);

        $stock[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $stockThisWeekQty,
            'thisWeekPrice' => $stockThisWeekPrice,
            'Weekend' => $stockLastWeekQty,
            'WeekendPrice' => $stockLastWeekPrice,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $stock,
    ]);
}




    public function expenseWeek(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;
        $validate = $request->validate([
            'year' => 'nullable|integer|min:2000',
        ]);
        $base = now();
        $year = (int) ($validate['year'] ?? $base->year);
        if ($year !== (int) $base->year) {
            $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
            $day = min($base->day, $daysInTargetMonth);
            $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
        }
        $month = $base->month;

        // Get start/end dates for current and last month
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();
        $lastMonth = $month == 1 ? 12 : $month - 1;
        $lastMonthYear = $month == 1 ? $year - 1 : $year;
        $lastStartDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->startOfMonth();
        $lastEndDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->endOfMonth();

        // Helper to get weekly sums for expense
        $getWeeklyexpense = function($start, $end) use ($proId) {
            $weeks = [];
            $weekRanges = [];
            $current = $start->copy();
            while ($current < $end) {
                $weekStart = $current->copy();
                $weekEnd = $current->copy()->addDays(6);
                if ($weekEnd > $end) $weekEnd = $end->copy();
                $weekRanges[] = [$weekStart, $weekEnd];
                $current = $weekEnd->copy()->addDay();
            }
            foreach ($weekRanges as [$ws, $we]) {
                $sum = DB::table('expense_masters as em')
                    ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
                    ->join('users as u', 'em.created_by', '=', 'u.id')
                    ->where('u.profile_id', $proId)
                    ->where('em.is_deleted', 0)
                    ->whereBetween('em.expense_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')])
                    ->sum('em.amount');
                $weeks[] = $sum;
            }
            return $weeks;
        };

        $thisMonthexpense = $getWeeklyexpense($startDate, $endDate);
        $lastMonthexpense = $getWeeklyexpense($lastStartDate, $lastEndDate);

        $weekCount = max(count($thisMonthexpense), count($lastMonthexpense));
        $data = [];
        for ($i = 0; $i < $weekCount; $i++) {
            $data[] = [
                'name' => 'Week ' . ($i + 1),
                'thisMonth' => $thisMonthexpense[$i] ?? 0,
                'lastMonth' => $lastMonthexpense[$i] ?? 0,
            ];
        }

        return response()->json([
            'message' => 'expense masters fetched successfully!',
            'status' => 200,
            'data' => $data
        ]);
    }

    public function expenseDay(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    // Get start and end of the current week and last week
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $today = $base;
    $currentWeekStart = $today->copy()->startOfWeek(); // Monday
    $currentWeekEnd = $today->copy()->endOfWeek();     // Sunday

    $lastWeekStart = $currentWeekStart->copy()->subWeek();
    $lastWeekEnd = $currentWeekEnd->copy()->subWeek();

    $data = [];

    // Loop through 7 days of the week (Mon → Sun)
    for ($i = 0; $i < 7; $i++) {
        // Current week day
        $thisDayStart = $currentWeekStart->copy()->addDays($i)->startOfDay();
        $thisDayEnd = $thisDayStart->copy()->endOfDay();

        // Last week same day
        $lastWeekDayStart = $lastWeekStart->copy()->addDays($i)->startOfDay();
        $lastWeekDayEnd = $lastWeekDayStart->copy()->endOfDay();

        // Get total expense for this day (current week)
        $thisWeekSum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $thisDayStart->format('Y-m-d H:i:s'),
                $thisDayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Get total expense for same day (last week)
        $lastWeekSum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $lastWeekDayStart->format('Y-m-d H:i:s'),
                $lastWeekDayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Add data to array
        $data[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $thisWeekSum,
            'Weekend' => $lastWeekSum,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}

public function expenseHour(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }

    $today = $base->copy()->startOfDay();
    $yesterday = $today->copy()->subDay();

    // Define your hourly slots (customize as needed)
    $timeSlots = [
        '07:00 AM',
        '11:00 AM',
        '04:00 PM',
        '09:00 PM',
        '02:00 AM',
        '06:00 AM',
    ];

    $data = [];

    foreach ($timeSlots as $slot) {
        // Parse slot into Carbon time
        $time = \Carbon\Carbon::parse($slot);

        // Create today's time range (1 hour window)
        $todayStart = $today->copy()->setTimeFromTimeString($time->format('H:i:s'));
        $todayEnd = $todayStart->copy()->addHour();

        // Create yesterday's same time range
        $yesterdayStart = $yesterday->copy()->setTimeFromTimeString($time->format('H:i:s'));
        $yesterdayEnd = $yesterdayStart->copy()->addHour();

        // Calculate today's total expense for this hour
        $todaySum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $todayStart->format('Y-m-d H:i:s'),
                $todayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Calculate yesterday's total expense for this hour
        $yesterdaySum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $yesterdayStart->format('Y-m-d H:i:s'),
                $yesterdayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Add to data array
        $data[] = [
            'name' => $slot,
            'today' => $todaySum,
            'yesterday' => $yesterdaySum,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}

public function expenseMonth(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $now = now();
    $currentYear = (int) ($validate['year'] ?? $now->year);
    $lastYear = $currentYear - 1;
    $currentMonth = $currentYear === (int) $now->year ? $now->month : 12;

    $data = [];

    // Loop from January to the current month
    for ($month = 1; $month <= $currentMonth; $month++) {
        // This year: start & end of month
        $startThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->startOfMonth();
        $endThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->endOfMonth();

        // Last year: same month
        $startLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->startOfMonth();
        $endLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->endOfMonth();

        // Calculate total expense for this year’s month
        $thisYearSum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $startThisYear->format('Y-m-d'),
                $endThisYear->format('Y-m-d')
            ])
            ->sum('em.amount');

        // Calculate total expense for last year’s same month
        $lastYearSum = DB::table('expense_masters as em')
            ->join('expense_items as ei', 'em.expense_id', '=', 'ei.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expense_date', [
                $startLastYear->format('Y-m-d'),
                $endLastYear->format('Y-m-d')
            ])
            ->sum('em.amount');

        // Push result for this month
        $data[] = [
            'name' => $startThisYear->format('F'), // e.g., January, February
            'thisYear' => $thisYearSum,
            'lastYear' => $lastYearSum,
        ];
    }

    return response()->json([
        'message' => 'expense masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}

public function profiteByHour(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }

    $today = $base->copy()->startOfDay();
    $yesterday = $today->copy()->subDay();

    $timeSlots = [
        '07:00 AM' => ['start' => '07:00:00', 'end' => '10:59:59'],
        '11:00 AM' => ['start' => '11:00:00', 'end' => '15:59:59'],
        '04:00 PM' => ['start' => '16:00:00', 'end' => '20:59:59'],
        '09:00 PM' => ['start' => '21:00:00', 'end' => '01:59:59'],
        '02:00 AM' => ['start' => '02:00:00', 'end' => '05:59:59'],
        '06:00 AM' => ['start' => '06:00:00', 'end' => '06:59:59'],
    ];

    $getHourlySum = function ($table, $amountField, $dateField, $proId, $day, $startTime, $endTime) {
        $query = DB::table($table)
            ->join('users as u', $table . '.created_by', '=', 'u.id')
            ->where($table . '.is_deleted', 0)
            ->where('u.profile_id', $proId);

        if ($startTime > $endTime) {
            $query->where(function ($q) use ($day, $startTime, $dateField, $table) {
                $q->whereRaw("TIME($table.created_at) BETWEEN ? AND ?", [$startTime, '23:59:59'])
                    ->whereRaw("DATE($table.$dateField) = ?", [$day->format('Y-m-d')]);
            })->orWhere(function ($q) use ($day, $endTime, $dateField, $table) {
                $q->whereRaw("TIME($table.created_at) BETWEEN ? AND ?", ['00:00:00', $endTime])
                    ->whereRaw("DATE($table.$dateField) = ?", [$day->copy()->addDay()->format('Y-m-d')]);
            });
        } else {
            $query->whereRaw("DATE($table.$dateField) = ?", [$day->format('Y-m-d')])
                ->whereRaw("TIME($table.created_at) BETWEEN ? AND ?", [$startTime, $endTime]);
        }

        return $query->sum($table . '.' . $amountField);
    };

    $data = [];
    foreach ($timeSlots as $label => $times) {
        $startTime = $times['start'];
        $endTime = $times['end'];

        $saleToday = $getHourlySum('order_masters', 'payment', 'order_date', $proId, $today, $startTime, $endTime);
        $purchaseToday = $getHourlySum('purchases', 'total_amount', 'purchase_date', $proId, $today, $startTime, $endTime);
        $expenseToday = $getHourlySum('expense_masters', 'amount', 'expense_date', $proId, $today, $startTime, $endTime);

        $saleYesterday = $getHourlySum('order_masters', 'payment', 'order_date', $proId, $yesterday, $startTime, $endTime);
        $purchaseYesterday = $getHourlySum('purchases', 'total_amount', 'purchase_date', $proId, $yesterday, $startTime, $endTime);
        $expenseYesterday = $getHourlySum('expense_masters', 'amount', 'expense_date', $proId, $yesterday, $startTime, $endTime);

        $data[] = [
            'name' => $label,
            'today' => $saleToday - $purchaseToday - $expenseToday,
            'yesterday' => $saleYesterday - $purchaseYesterday - $expenseYesterday,
        ];
    }

    return response()->json([
        'message' => 'Profit by hour fetched successfully!',
        'status' => 200,
        'data' => $data,
    ]);
}

public function profiteByDay(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }

    $startOfWeek = $base->copy()->startOfWeek();
    $startOfLastWeek = $startOfWeek->copy()->subWeek();

    $getDailySum = function ($table, $amountField, $dateField, $proId, $date) {
        return DB::table($table)
            ->join('users as u', $table . '.created_by', '=', 'u.id')
            ->where($table . '.is_deleted', 0)
            ->where('u.profile_id', $proId)
            ->where($table . '.' . $dateField, $date->format('Y-m-d'))
            ->sum($table . '.' . $amountField);
    };

    $data = [];
    for ($i = 0; $i < 7; $i++) {
        $currentDay = $startOfWeek->copy()->addDays($i);
        $lastWeekDay = $startOfLastWeek->copy()->addDays($i);

        $saleThis = $getDailySum('order_masters', 'payment', 'order_date', $proId, $currentDay);
        $purchaseThis = $getDailySum('purchases', 'total_amount', 'purchase_date', $proId, $currentDay);
        $expenseThis = $getDailySum('expense_masters', 'amount', 'expense_date', $proId, $currentDay);

        $saleLast = $getDailySum('order_masters', 'payment', 'order_date', $proId, $lastWeekDay);
        $purchaseLast = $getDailySum('purchases', 'total_amount', 'purchase_date', $proId, $lastWeekDay);
        $expenseLast = $getDailySum('expense_masters', 'amount', 'expense_date', $proId, $lastWeekDay);

        $data[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $saleThis - $purchaseThis - $expenseThis,
            'Weekend' => $saleLast - $purchaseLast - $expenseLast,
        ];
    }

    return response()->json([
        'message' => 'Profit by day fetched successfully!',
        'status' => 200,
        'data' => $data,
    ]);
}

public function profiteByWeek(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;
    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $base = now();
    $year = (int) ($validate['year'] ?? $base->year);
    if ($year !== (int) $base->year) {
        $daysInTargetMonth = \Carbon\Carbon::create($year, $base->month, 1)->daysInMonth;
        $day = min($base->day, $daysInTargetMonth);
        $base = \Carbon\Carbon::create($year, $base->month, $day, $base->hour, $base->minute, $base->second);
    }
    $month = $base->month;

    $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
    $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

    $lastMonth = $month == 1 ? 12 : $month - 1;
    $lastMonthYear = $month == 1 ? $year - 1 : $year;
    $lastStartDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->startOfMonth();
    $lastEndDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->endOfMonth();

    $getWeeklyProfit = function ($proId, $start, $end) {
        $weeks = [];
        $current = $start->copy();
        while ($current <= $end) {
            $ws = $current->copy();
            $we = $current->copy()->addDays(6);
            if ($we > $end) $we = $end->copy();

            $sale = DB::table('order_masters as om')
                ->join('users as u', 'om.created_by', '=', 'u.id')
                ->where('om.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('om.order_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')])
                ->sum('om.payment');

            $purchase = DB::table('purchases as p')
                ->join('users as u', 'p.created_by', '=', 'u.id')
                ->where('p.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('p.purchase_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')])
                ->sum('p.total_amount');

            $expense = DB::table('expense_masters as em')
                ->join('users as u', 'em.created_by', '=', 'u.id')
                ->where('em.is_deleted', 0)
                ->where('u.profile_id', $proId)
                ->whereBetween('em.expense_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')])
                ->sum('em.amount');

            $weeks[] = $sale - $purchase - $expense;
            $current = $we->copy()->addDay();
        }
        return $weeks;
    };

    $thisMonth = $getWeeklyProfit($proId, $startDate, $endDate);
    $lastMonthData = $getWeeklyProfit($proId, $lastStartDate, $lastEndDate);

    $weekCount = max(count($thisMonth), count($lastMonthData));
    $data = [];
    for ($i = 0; $i < $weekCount; $i++) {
        $data[] = [
            'name' => 'Week ' . ($i + 1),
            'thisMonth' => $thisMonth[$i] ?? 0,
            'lastMonth' => $lastMonthData[$i] ?? 0,
        ];
    }

    return response()->json([
        'message' => 'Profit by week fetched successfully!',
        'status' => 200,
        'data' => $data,
    ]);
}

public function profiteByMonth(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $validate = $request->validate([
        'year' => 'nullable|integer|min:2000',
    ]);
    $now = now();
    $currentYear = (int) ($validate['year'] ?? $now->year);
    $lastYear = $currentYear - 1;
    $currentMonth = $currentYear === (int) $now->year ? $now->month : 12;

    $getMonthlySum = function ($table, $amountField, $dateField, $proId, $start, $end) {
        return DB::table($table)
            ->join('users as u', $table . '.created_by', '=', 'u.id')
            ->where($table . '.is_deleted', 0)
            ->where('u.profile_id', $proId)
            ->whereBetween($table . '.' . $dateField, [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->sum($table . '.' . $amountField);
    };

    $data = [];
    for ($m = 1; $m <= $currentMonth; $m++) {
        $startThis = \Carbon\Carbon::create($currentYear, $m, 1)->startOfMonth();
        $endThis = $startThis->copy()->endOfMonth();
        $startLast = \Carbon\Carbon::create($lastYear, $m, 1)->startOfMonth();
        $endLast = $startLast->copy()->endOfMonth();

        $saleThis = $getMonthlySum('order_masters', 'payment', 'order_date', $proId, $startThis, $endThis);
        $purchaseThis = $getMonthlySum('purchases', 'total_amount', 'purchase_date', $proId, $startThis, $endThis);
        $expenseThis = $getMonthlySum('expense_masters', 'amount', 'expense_date', $proId, $startThis, $endThis);

        $saleLast = $getMonthlySum('order_masters', 'payment', 'order_date', $proId, $startLast, $endLast);
        $purchaseLast = $getMonthlySum('purchases', 'total_amount', 'purchase_date', $proId, $startLast, $endLast);
        $expenseLast = $getMonthlySum('expense_masters', 'amount', 'expense_date', $proId, $startLast, $endLast);

        $data[] = [
            'name' => $startThis->format('F'),
            'thisYear' => $saleThis - $purchaseThis - $expenseThis,
            'lastYear' => $saleLast - $purchaseLast - $expenseLast,
        ];
    }

    return response()->json([
        'message' => 'Profit by month fetched successfully!',
        'status' => 200,
        'data' => $data,
    ]);
}



}
