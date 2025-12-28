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
        $expanse_items = DB::table('expanse_items')
            ->join('expanse_types', 'expanse_items.expanse_type_id', '=', 'expanse_types.expanse_type_id')
            ->where('created_by', $uid)
            ->where('expanse_items.is_deleted', 0)
            ->get();
        if (!$expanse_items) {
            return response()->json([
                'message' => 'expanse item get fail!',
                'status' => 404
            ]);
        }
        return response()->json([
            'message' => 'expanse item geted successfully!',
            'status' => 200,
            'data' => $expanse_items
        ]);
    }

    public function PopularExpanse()
    {
        $items = DB::table('expanse_items')
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
            'message' => 'popular expanse item geted successfully!',
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
        $expanse_items = DB::table('expanse_items')->where('expanse_id', $id)
            ->join('expanse_types', 'expanse_items.expanse_type_id', '=', 'expanse_types.expanse_type_id')
            ->where('created_by', $uid)
            ->get();
        if (!$expanse_items) {
            return response()->json([
                'message' => 'expanse item get fail!',
                'status' => 404
            ]);
        }
        return response()->json([
            'message' => 'expanse item geted successfully!',
            'status' => 200,
            'data' => $expanse_items
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
