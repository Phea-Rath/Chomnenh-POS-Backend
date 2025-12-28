<?php

namespace App\Http\Controllers;

use App\Models\StockTypes;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockTypeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        // $page = 2;
        $stock_types = DB::table('stock_types')
            ->join('users', "stock_types.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->whereIn('users.profile_id', [1, $proId])
            ->where('stock_types.is_deleted', 0)
            ->select('stock_types.*')
            // ->paginate($page);
            ->get();
        if (count($stock_types) == 0) {
            // $stock_types = DB::table('stock_types')
            //     ->where('stock_types.created_by', '=', 0)
            //     ->select('stock_types.*')
            //     ->get();
            // if (count($stock_types) == 0) {
            return response()->json([
                'message' => 'No stock types found',
                'status' => 404,
                'data' => [],
            ]);
            // }
        }
        return response()->json([
            'message' => 'StockTypes selected successfully',
            'status' => 200,
            'data' => array_reverse($stock_types->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'stock_type_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = StockTypes::create([
            'stock_type_name' => $validated['stock_type_name'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'StockType created successfully!',
            'status' => 200,
            'data' => $data,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $stock_types = StockTypes::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$stock_types) {
            return response()->json([
                'message' => 'StockType not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'StockType show successfully!',
            'status' => 200,
            'data' => $stock_types,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        $stock_types = StockTypes::find($id);

        if (!$stock_types) {
            return response()->json([
                "message" => "This stock type not found!",
            ], 404);
        }

        $validated = $request->validate([
            'stock_type_name' => 'required|string|max:255',
        ]);

        $stock_types->update($validated);

        return response()->json([
            "message" => "StockType updated successfully",
            "status" => 200,
            "data" => $stock_types,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $stock_types = StockTypes::find($id);
        if (!$stock_types) {
            return response()->json([
                "message" => "This stock type not found!",
            ], 404);
        }

        $stock_types->is_deleted = 1;
        $stock_types->save();
        return response()->json([
            "message" => "StockType deleted successfully",
            "status" => 200,
            "data" => $stock_types,
        ], 200);
    }
}
