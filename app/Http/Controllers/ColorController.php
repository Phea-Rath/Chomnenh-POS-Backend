<?php

namespace App\Http\Controllers;

use App\Models\Colors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ColorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        // $page = 2;
        $colors = DB::table('colors')
            ->join('users', "colors.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('users.profile_id', '=', $proId)
            ->where('colors.is_deleted', 0)
            ->select('colors.*')
            // ->paginate($page);
            ->get();
        if (count($colors) == 0) {
            return response()->json([
                'message' => 'Colors not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $colors
            ]);
        }
        return response()->json([
            'message' => 'Colors selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($colors->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'color_name' => 'required|string|max:255',
            'color_pick' => 'required|string|max:255',
            'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = Colors::create([
            'color_name' => $validated['color_name'],
            'color_pick' => $validated['color_pick'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'Color created successfully!',
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
        $colors = Colors::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$colors) {
            return response()->json([
                'message' => 'Color not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Color show successfully!',
            'status' => 200,
            'data' => $colors,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        $colors = Colors::find($id);

        if (!$colors) {
            return response()->json([
                "message" => "This color not found!",
            ], 404);
        }

        $validated = $request->validate([
            'color_name' => 'required|string|max:255',
            'color_pick' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        $colors->update($validated);

        return response()->json([
            "message" => "Color updated successfully",
            "status" => 200,
            "data" => $colors,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $colors = Colors::find($id);
        if (!$colors) {
            return response()->json([
                "message" => "This color not found!",
            ], 404);
        }

        $colors->is_deleted = 1;
        $colors->save();
        return response()->json([
            "message" => "Color deleted successfully",
            "status" => 200,
            "data" => $colors,
        ], 200);
    }
}
