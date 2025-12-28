<?php

namespace App\Http\Controllers;

use App\Models\ExpanseTypes;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpanseTypeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        // $page = 2;
        $expanse_types = DB::table('expanse_types')
            ->where('is_deleted', 0)
            ->where('created_by', $uid)
            // ->paginate($page);
            ->get();
        if (count($expanse_types) == 0) {
            return response()->json([
                'message' => 'ExpanseTypes not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $expanse_types
            ]);
        }
        return response()->json([
            'message' => 'ExpanseTypes selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($expanse_types->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'expanse_type_name' => 'required|string|max:255',
            'created_by' => 'required|integer',
        ]);
        // dd($validated);
        $data = ExpanseTypes::create([
            'expanse_type_name' => $validated['expanse_type_name'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'ExpanseType created successfully!',
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
        $expanse_types = ExpanseTypes::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$expanse_types) {
            return response()->json([
                'message' => 'ExpanseType not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'ExpanseType show successfully!',
            'status' => 200,
            'data' => $expanse_types,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        $expanse_types = ExpanseTypes::find($id);

        if (!$expanse_types) {
            return response()->json([
                "message" => "This expanse type not found!",
            ], 404);
        }

        $validated = $request->validate([
            'expanse_type_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        $expanse_types->update($validated);

        return response()->json([
            "message" => "ExpanseType updated successfully",
            "status" => 200,
            "data" => $expanse_types,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expanse_types = ExpanseTypes::find($id);
        if (!$expanse_types) {
            return response()->json([
                "message" => "This expanse type not found!",
            ], 404);
        }

        $expanse_types->is_deleted = 1;
        $expanse_types->save();
        return response()->json([
            "message" => "ExpanseType deleted successfully",
            "status" => 200,
            "data" => $expanse_types,
        ], 200);
    }
}
