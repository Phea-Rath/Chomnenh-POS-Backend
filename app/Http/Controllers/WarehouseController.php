<?php

namespace App\Http\Controllers;

use App\Models\Warehouses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class WarehouseController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        // $page = 2;
        $warehouses = DB::table('warehouses')
            ->join('users', 'warehouses.created_by', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->whereIn('users.profile_id', [1, $proId])
            ->where('warehouses.is_deleted', 0)
            // ->whereIn('warehouses.created_by', [0, $user->id])
            ->select('warehouses.*')
            //->paginate($page);
            ->get();

        if ($warehouses->isEmpty()) {
            // $warehouses = DB::table('warehouses')
            //     ->where('warehouses.created_by', '=', 1)
            //     ->select('warehouses.*')
            //     //->paginate($page);
            //     ->get();

            // if ($warehouses->isEmpty()) {
            return response()->json([
                'message' => 'No warehouses found',
                'status' => 404,
                'data' => [],
            ]);
            // }
        }
        return response()->json([
            'message' => 'Warehouses selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($warehouses->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'warehouse_name' => 'required|string|max:255',
            'status' => 'required|string|max:255',
            'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = Warehouses::create([
            'warehouse_name' => $validated['warehouse_name'],
            'status' => $validated['status'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'Warehouse created successfully!',
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
        $proId = $user->profile_id;
        $warehouses = Warehouses::join('users', "warehouses.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('users.profile_id', '=', $proId)
            ->select('warehouses.*')
            ->where('warehouses.is_deleted', 0)
            ->where('created_by', $uid)->find($id);
        if (!$warehouses) {
            return response()->json([
                'message' => 'Warehouse not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Warehouse show successfully!',
            'status' => 200,
            'data' => $warehouses,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        $warehouses = Warehouses::find($id);

        if (!$warehouses) {
            return response()->json([
                "message" => "This warehouse not found!",
            ], 404);
        }

        $validated = $request->validate([
            'warehouse_name' => 'required|string|max:255',
            'status' => 'required|string|max:255',
        ]);

        $warehouses->update($validated);

        return response()->json([
            "message" => "Warehouse updated successfully",
            "status" => 200,
            "data" => $warehouses,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $warehouses = Warehouses::find($id);
        if (!$warehouses) {
            return response()->json([
                "message" => "This warehouse not found!",
            ], 404);
        }

        $warehouses->is_deleted = 1;
        $warehouses->save();
        return response()->json([
            "message" => "Warehouse deleted successfully",
            "status" => 200,
            "data" => $warehouses,
        ], 200);
    }
}
