<?php

namespace App\Http\Controllers;

use App\Events\PrivateChannelEvent;
use App\Events\PrivateMessageEvent;
use App\Events\SizeCreated;
use App\Models\Sizes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SizeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        // dd($uid);
        $proId = $user->profile_id;
        // $page = 2;
        $sizes = DB::table('sizes')
            ->join('users', "sizes.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('users.profile_id', '=', $proId)
            ->where('sizes.is_deleted', 0)
            ->select('sizes.*')
            // ->paginate($page);
            ->get();
        if (count($sizes) == 0) {
            return response()->json([
                'message' => 'Sizes not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $sizes
            ]);
        }
        return response()->json([
            'message' => 'Sizes selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($sizes->toArray()),
        ]);
    }


    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $validated = $request->validate([
            'size_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = Sizes::create([
            'size_name' => $validated['size_name'],
            'created_by' => $uid,
        ]);

        // event(new PrivateChannelEvent("Create size successfully", 1));

        // broadcast(new PrivateChannelEvent($data, (int)$proId))->toOthers();

        return response()->json([
            'message' => 'Size created successfully!',
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
        $sizes = Sizes::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$sizes) {
            return response()->json([
                'message' => 'Size not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Size show successfully!',
            'status' => 200,
            'data' => $sizes,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        $sizes = Sizes::find($id);

        if (!$sizes) {
            return response()->json([
                "message" => "This size not found!",
            ], 404);
        }

        $validated = $request->validate([
            'size_name' => 'required|string|max:255',
        ]);

        $sizes->update($validated);

        return response()->json([
            "message" => "Size updated successfully",
            "status" => 200,
            "data" => $sizes,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sizes = Sizes::find($id);
        if (!$sizes) {
            return response()->json([
                "message" => "This size not found!",
            ], 404);
        }

        $sizes->is_deleted = 1;
        $sizes->save();
        return response()->json([
            "message" => "Size deleted successfully",
            "status" => 200,
            "data" => $sizes,
        ], 200);
    }
}
