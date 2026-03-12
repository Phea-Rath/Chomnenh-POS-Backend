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
        $proId = $user->profile_id;
        $expense_items = DB::table('expense_items as ei')
            ->join('expense_types as et', 'ei.expense_type_id', '=', 'et.expense_type_id')
            ->join('expense_masters as em', 'ei.expense_id', '=', 'em.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->where('ei.is_deleted', 0)
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
        $user = Auth::user();
        $proId = $user->profile_id;
        $items = DB::table('expense_items as ei')
            ->join('expense_masters as em', 'ei.expense_id', '=', 'em.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
            ->select(
                'ei.description',
                DB::raw('SUM(ei.sub_total) as total_price'),
                DB::raw('SUM(ei.quantity) as quantity')
            )
            ->groupBy('ei.description')
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
        $proId = $user->profile_id;
        $expense_items = DB::table('expense_items as ei')
            ->where('ei.expense_id', $id)
            ->join('expense_types as et', 'ei.expense_type_id', '=', 'et.expense_type_id')
            ->join('expense_masters as em', 'ei.expense_id', '=', 'em.expense_id')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('u.profile_id', $proId)
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
