<?php

namespace App\Http\Controllers;

use App\Models\ExpanseItems;
use Auth;
use DB;
use Illuminate\Http\Request;

class ExpanseItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $expense_items = DB::table('expense_items')
            ->join('expense_types', 'expense_items.expense_type_id', '=', 'expense_types.expense_type_id')
            ->where('created_by', $uid)
            ->where('expense_items.is_deleted', 0)
            ->get();
        if (!$expense_items) {
            return response()->json([
                'message' => 'expense item get fail!',
                'status' => 404
            ]);
        }
        return response()->json([
            'message' => 'expense item geted successfully!',
            'status' => 200,
            'data' => $expense_items
        ]);
    }

    public function PopularExpanse()
    {
        $items = DB::table('expense_items')
            ->select(
                'description',
                DB::raw('SUM(sub_total) as total_price'),
                DB::raw('SUM(quantity) as quantity')
            )
            ->groupBy('description')
            ->orderByDesc('total_price')
            ->limit(5)
            ->get();

        return response()->json([
            'message' => 'popular expense item geted successfully!',
            'status' => 200,
            'data' => $items
        ]);
    }
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $expense_items = DB::table('expense_items')->where('expense_id', $id)
            ->join('expense_types', 'expense_items.expense_type_id', '=', 'expense_types.expense_type_id')
            ->where('created_by', $uid)
            ->get();
        if (!$expense_items) {
            return response()->json([
                'message' => 'expense item get fail!',
                'status' => 404
            ]);
        }
        return response()->json([
            'message' => 'expense item geted successfully!',
            'status' => 200,
            'data' => $expense_items
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
