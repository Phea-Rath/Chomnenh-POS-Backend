<?php

namespace App\Http\Controllers;

use App\Models\Scales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ScaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        // $page = 2;
        $scales = DB::table('scales')
            ->join('users', "scales.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('users.profile_id', '=', $proId)
            ->where('scales.is_deleted', 0)
            ->select('scales.*')
            // ->paginate($page);
            ->get();
        if (count($scales) == 0) {
            return response()->json([
                'message' => 'Scales not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $scales
            ]);
        }
        return response()->json([
            'message' => 'Scales selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($scales->toArray()),
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
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'scale_name' => 'required|string|max:255',
            'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = Scales::create([
            'scale_name' => $validated['scale_name'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'Scale created successfully!',
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
        $scales = Scales::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$scales) {
            return response()->json([
                'message' => 'Scale not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Scale show successfully!',
            'status' => 200,
            'data' => $scales,
        ], 201);
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
        $scales = Scales::find($id);

        if (!$scales) {
            return response()->json([
                "message" => "This scale not found!",
            ], 404);
        }

        $validated = $request->validate([
            'scale_name' => 'required|string|max:255',
        ]);

        $scales->update($validated);

        return response()->json([
            "message" => "Scale updated successfully",
            "status" => 200,
            "data" => $scales,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $scales = Scales::find($id);
        if (!$scales) {
            return response()->json([
                "message" => "This scale not found!",
            ], 404);
        }

        $scales->is_deleted = 1;
        $scales->save();
        return response()->json([
            "message" => "Scale deleted successfully",
            "status" => 200,
            "data" => $scales,
        ], 200);
    }
}
