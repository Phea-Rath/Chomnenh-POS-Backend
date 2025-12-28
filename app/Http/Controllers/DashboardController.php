<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    //
    public function showCard()
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $currentYear = now()->year;

        // --- Totals ---
        $stockData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('p.id', $proId)
            ->where('sm.stock_created_by', $uid)
            ->whereYear('sm.stock_date', $currentYear)
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
                'message' => 'expanse data get fail!',
                'status' => 404
            ]);
        }

        // --- Monthly breakdown ---
        $monthlyData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('p.id', $proId)
            ->where('sm.stock_created_by', $uid)
            ->whereYear('sm.stock_date', $currentYear)
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

        // Build month list from Jan -> current month
        $months = [];
        for ($m = 1; $m <= now()->month; $m++) {
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
            'message' => 'expanse data geted successfully!',
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


    public function showGraphic(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;

        // Validate month/year as integers
        $validate = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000',
        ]);

        $month = $validate['month'];
        $year = $validate['year'];

        // Build start and end date for selected month
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate   = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();

        // --- Totals ---
        $stockData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.stock_created_by', $uid)
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
                'message' => 'expanse data get fail!',
                'status' => 404
            ]);
        }

        // --- Monthly breakdown (from Jan to selected month) ---
        $monthlyData = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.stock_created_by', $uid)
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
            'message' => 'expanse data geted successfully!',
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

    public function saleByWeek()
{
    $user = Auth::user();
    $uid = $user->id;
    $month = now()->month;
    $year = now()->year;

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
    $getWeeklySum = function ($uid, $start, $end, $usePrice = false) use ($getWeekRanges) {
        $weeks = [];
        $weekRanges = $getWeekRanges($start, $end);

        foreach ($weekRanges as [$ws, $we]) {
            $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->where('oi.is_deleted', 0)
                ->where('om.created_by', $uid)
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
    $salesThisMonth = $getWeeklySum($uid, $startDate, $endDate, false); // Sales quantity
    $salesThisMonthPrice = $getWeeklySum($uid, $startDate, $endDate, true); // Sales price
    $salesLastMonth = $getWeeklySum($uid, $lastStartDate, $lastEndDate, false);
    $salesLastMonthPrice = $getWeeklySum($uid, $lastStartDate, $lastEndDate, true);

    // Build response arrays
    $weekCount = max(
        count($salesThisMonth), 
        count($salesLastMonth), 
    );

    $sales = [];
    $stock = [];

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
        'message' => 'Expanse data fetched successfully!',
        'status' => 200,
        'data' => $sales,
        // 'stock' => $stock
    ]);
}
    public function purchaseByWeek()
{
    $user = Auth::user();
    $uid = $user->id;
    $month = now()->month;
    $year = now()->year;

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
    $getWeeklySum = function ($uid, $start, $end, $usePrice = false) use ($getWeekRanges) {
        $weeks = [];
        $weekRanges = $getWeekRanges($start, $end);

        foreach ($weekRanges as [$ws, $we]) {
            $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->where('pd.is_deleted', 0)
                ->where('p.created_by', $uid)
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

    $stockThisMonth = $getWeeklySum($uid, $startDate, $endDate, false); // Stock quantity
    $stockThisMonthPrice = $getWeeklySum($uid, $startDate, $endDate, true);
    $stockLastMonth = $getWeeklySum($uid, $lastStartDate, $lastEndDate, false);
    $stockLastMonthPrice = $getWeeklySum($uid, $lastStartDate, $lastEndDate, true);

    // Build response arrays
    $weekCount = max(
        count($stockThisMonth), 
        count($stockLastMonth)
    );

    $sales = [];
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
        'message' => 'Expanse data fetched successfully!',
        'status' => 200,
        'data' => $stock
    ]);
}


    public function saleByMonth()
{
    $user = Auth::user();
    $uid = $user->id;

    $currentYear = now()->year;
    $lastYear = $currentYear - 1;
    $currentMonth = now()->month;

    $data = [];

    // Helper function to get monthly total (quantity or price)
    $getMonthlySum = function ($uid, $startDate, $endDate, $usePrice = false) {
         $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->where('oi.is_deleted', 0)
                ->where('om.created_by', $uid)
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
        $salesThisYearQty = $getMonthlySum($uid, $startThisYear, $endThisYear, false);
        $salesThisYearPrice = $getMonthlySum($uid, $startThisYear, $endThisYear, true);
        $salesLastYearQty = $getMonthlySum($uid, $startLastYear, $endLastYear, false);
        $salesLastYearPrice = $getMonthlySum($uid, $startLastYear, $endLastYear, true);

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
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}
    public function purchaseByMonth()
{
    $user = Auth::user();
    $uid = $user->id;

    $currentYear = now()->year;
    $lastYear = $currentYear - 1;
    $currentMonth = now()->month;

    $data = [];

    // Helper function to get monthly total (quantity or price)
    $getMonthlySum = function ($uid, $startDate, $endDate, $usePrice = false) {
         $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->where('pd.is_deleted', 0)
                ->where('p.created_by', $uid)
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

        $stockThisYearQty = $getMonthlySum($uid, $startThisYear, $endThisYear, false);
        $stockThisYearPrice = $getMonthlySum($uid, $startThisYear, $endThisYear, true);
        $stockLastYearQty = $getMonthlySum($uid, $startLastYear, $endLastYear, false);
        $stockLastYearPrice = $getMonthlySum($uid, $startLastYear, $endLastYear, true);

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
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}


public function saleByHour()
{
    $user = Auth::user();
    $uid = $user->id;

    $today = \Carbon\Carbon::today();
    $yesterday = \Carbon\Carbon::yesterday();

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
    $getHourlySum = function ($uid, $day, $startTime, $endTime, $usePrice = false) {
    $query = DB::table('order_items as oi')
        ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
        ->where('oi.is_deleted', 0)
        ->where('om.created_by', $uid);

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
            'today' => $getHourlySum($uid, $today, $startTime, $endTime, false),
            'todayPrice' => $getHourlySum($uid, $today, $startTime, $endTime, true),
            'yesterday' => $getHourlySum($uid, $yesterday, $startTime, $endTime, false),
            'yesterdayPrice' => $getHourlySum($uid, $yesterday, $startTime, $endTime, true),
        ];
    }

    return response()->json([
        'message' => 'Expanse masters fetched successfully!',
        'status' => 200,
        'data' => $sales,
        
    ]);
}


public function purchaseByHour()
{
    $user = Auth::user();
    $uid = $user->id;

    $today = \Carbon\Carbon::today();
    $yesterday = \Carbon\Carbon::yesterday();

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
    $getHourlySum = function ($uid, $day, $startTime, $endTime, $usePrice = false) {
    $query = DB::table('purchase_details as pd')
        ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
        ->where('pd.is_deleted', 0)
        ->where('p.created_by', $uid);

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
            'today' => $getHourlySum($uid, $today, $startTime, $endTime, false),
            'todayPrice' => $getHourlySum($uid, $today, $startTime, $endTime, true),
            'yesterday' => $getHourlySum($uid, $yesterday, $startTime, $endTime, false),
            'yesterdayPrice' => $getHourlySum($uid, $yesterday, $startTime, $endTime, true),
        ];
    }

    return response()->json([
        'message' => 'Expanse masters fetched successfully!',
        'status' => 200,
        'data' =>  $stock,
        
    ]);
}


public function saleByDay()
{
    $user = Auth::user();
    $uid = $user->id;

    // Get current date and current week start/end (Monday to Sunday)
    $today = now();
    $startOfWeek = $today->copy()->startOfWeek();
    $endOfWeek = $today->copy()->endOfWeek();

    // Previous week start/end
    $startOfLastWeek = $startOfWeek->copy()->subWeek();
    $endOfLastWeek = $endOfWeek->copy()->subWeek();

    // Helper: Get daily sum (quantity or price)
    $getDailySum = function ($uid, $date, $usePrice = false) {
        $query = DB::table('order_items as oi')
                ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
                ->where('oi.is_deleted', 0)
                ->where('om.created_by', $uid)
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
    // $stock = [];

    for ($i = 0; $i < 7; $i++) {
        $currentDay = $startOfWeek->copy()->addDays($i);
        $lastWeekDay = $startOfLastWeek->copy()->addDays($i);

        // Sales quantity and price for current week and last week
        $salesThisWeekQty = $getDailySum($uid, $currentDay, false);
        $salesThisWeekPrice = $getDailySum($uid, $currentDay, true);
        $salesLastWeekQty = $getDailySum($uid, $lastWeekDay, false);
        $salesLastWeekPrice = $getDailySum($uid, $lastWeekDay, true);

        $sales[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $salesThisWeekQty,
            'thisWeekPrice' => $salesThisWeekPrice,
            'Weekend' => $salesLastWeekQty,
            'WeekendPrice' => $salesLastWeekPrice,
        ];
    }

    return response()->json([
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' =>  $sales,
    ]);
}


public function purchaseByDay()
{
    $user = Auth::user();
    $uid = $user->id;

    // Get current date and current week start/end (Monday to Sunday)
    $today = now();
    $startOfWeek = $today->copy()->startOfWeek();
    $endOfWeek = $today->copy()->endOfWeek();

    // Previous week start/end
    $startOfLastWeek = $startOfWeek->copy()->subWeek();
    $endOfLastWeek = $endOfWeek->copy()->subWeek();

    // Helper: Get daily sum (quantity or price)
    $getDailySum = function ($uid, $date, $usePrice = false) {
        $query = DB::table('purchase_details as pd')
                ->join('purchases as p', 'pd.purchase_id', '=', 'p.purchase_id')
                ->where('pd.is_deleted', 0)
                ->where('p.created_by', $uid)
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
        $stockThisWeekQty = $getDailySum($uid, $currentDay, false);
        $stockThisWeekPrice = $getDailySum($uid, $currentDay, true);
        $stockLastWeekQty = $getDailySum($uid, $lastWeekDay, false);
        $stockLastWeekPrice = $getDailySum($uid, $lastWeekDay, true);

        $stock[] = [
            'name' => 'Day ' . ($i + 1),
            'thisWeek' => $stockThisWeekQty,
            'thisWeekPrice' => $stockThisWeekPrice,
            'Weekend' => $stockLastWeekQty,
            'WeekendPrice' => $stockLastWeekPrice,
        ];
    }

    return response()->json([
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $stock,
    ]);
}




    public function expanseWeek(){
        $user = Auth::user();
        $uid = $user->id;
        $month = now()->month;
        $year = now()->year;

        // Get start/end dates for current and last month
        $startDate = \Carbon\Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = \Carbon\Carbon::create($year, $month, 1)->endOfMonth();
        $lastMonth = $month == 1 ? 12 : $month - 1;
        $lastMonthYear = $month == 1 ? $year - 1 : $year;
        $lastStartDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->startOfMonth();
        $lastEndDate = \Carbon\Carbon::create($lastMonthYear, $lastMonth, 1)->endOfMonth();

        // Helper to get weekly sums for expanse
        $getWeeklyExpanse = function($start, $end) use ($uid) {
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
                $sum = DB::table('expanse_masters as em')
                    ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
                    ->where('em.created_by', $uid)
                    ->where('em.is_deleted', 0)
                    ->whereBetween('em.expanse_date', [$ws->format('Y-m-d'), $we->format('Y-m-d')])
                    ->sum('em.amount');
                $weeks[] = $sum;
            }
            return $weeks;
        };

        $thisMonthExpanse = $getWeeklyExpanse($startDate, $endDate);
        $lastMonthExpanse = $getWeeklyExpanse($lastStartDate, $lastEndDate);

        $weekCount = max(count($thisMonthExpanse), count($lastMonthExpanse));
        $data = [];
        for ($i = 0; $i < $weekCount; $i++) {
            $data[] = [
                'name' => 'Week ' . ($i + 1),
                'thisMonth' => $thisMonthExpanse[$i] ?? 0,
                'lastMonth' => $lastMonthExpanse[$i] ?? 0,
            ];
        }

        return response()->json([
            'message' => 'expanse masters fetched successfully!',
            'status' => 200,
            'data' => $data
        ]);
    }

    public function expanseDay()
{
    $user = Auth::user();
    $uid = $user->id;

    // Get start and end of the current week and last week
    $today = now();
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

        // Get total expanse for this day (current week)
        $thisWeekSum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
                $thisDayStart->format('Y-m-d H:i:s'),
                $thisDayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Get total expanse for same day (last week)
        $lastWeekSum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
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
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}

public function expanseHour()
{
    $user = Auth::user();
    $uid = $user->id;

    $today = \Carbon\Carbon::today();
    $yesterday = \Carbon\Carbon::yesterday();

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

        // Calculate today's total expanse for this hour
        $todaySum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
                $todayStart->format('Y-m-d H:i:s'),
                $todayEnd->format('Y-m-d H:i:s')
            ])
            ->sum('em.amount');

        // Calculate yesterday's total expanse for this hour
        $yesterdaySum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
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
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}

public function expanseMonth()
{
    $user = Auth::user();
    $uid = $user->id;

    $currentYear = now()->year;
    $lastYear = $currentYear - 1;
    $currentMonth = now()->month;

    $data = [];

    // Loop from January to the current month
    for ($month = 1; $month <= $currentMonth; $month++) {
        // This year: start & end of month
        $startThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->startOfMonth();
        $endThisYear = \Carbon\Carbon::create($currentYear, $month, 1)->endOfMonth();

        // Last year: same month
        $startLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->startOfMonth();
        $endLastYear = \Carbon\Carbon::create($lastYear, $month, 1)->endOfMonth();

        // Calculate total expanse for this year’s month
        $thisYearSum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
                $startThisYear->format('Y-m-d'),
                $endThisYear->format('Y-m-d')
            ])
            ->sum('em.amount');

        // Calculate total expanse for last year’s same month
        $lastYearSum = DB::table('expanse_masters as em')
            ->join('expanse_items as ei', 'em.expanse_id', '=', 'ei.expanse_id')
            ->where('em.created_by', $uid)
            ->where('em.is_deleted', 0)
            ->whereBetween('em.expanse_date', [
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
        'message' => 'expanse masters fetched successfully!',
        'status' => 200,
        'data' => $data
    ]);
}





}
