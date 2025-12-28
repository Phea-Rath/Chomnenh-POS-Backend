<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Rating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class RatingController extends Controller
{
    //
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role_id;
        $proId = $user->profile_id;

        if ($role == 1) {
            $ratings = DB::table('ratings')
                ->join('users', 'ratings.user_id', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('ratings.*')
                ->get();
        } else {
            $ratings = DB::table('ratings')
                ->join('users', 'ratings.user_id', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->where('ratings.user_id', $uid)
                ->select('ratings.*')
                ->get();
        }

        if (count($ratings) == 0) {
            return response()->json([
                'message' => 'Ratings not found!',
                'status' => 404,
                'data' => $ratings
            ], 404);
        }

        return response()->json([
            'message' => 'Ratings selected successfully',
            'status' => 200,
            'data' => array_reverse($ratings->toArray()),
        ], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;

        $validated = $request->validate([
            'item_id' => 'required|integer',
            'rating' => 'required|numeric|min:0|max:5',
            'comment' => 'nullable|string',
        ]);

        $data = Rating::create([
            'item_id' => $validated['item_id'],
            'user_id' => $uid,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json([
            'message' => 'Rating created successfully!',
            'status' => 200,
            'data' => $data,
        ], 201);
    }

    public function show(string $id)
    {
        $rating = Rating::find($id);

        if (!$rating || (isset($rating->is_deleted) && $rating->is_deleted)) {
            return response()->json([
                'message' => 'Rating not found!',
            ], 404);
        }

        return response()->json([
            'message' => 'Rating retrieved successfully!',
            'status' => 200,
            'data' => $rating,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $rating = Rating::find($id);

        if (!$rating) {
            return response()->json([
                'message' => 'Rating not found!',
            ], 404);
        }

        $validated = $request->validate([
            'rating' => 'required|numeric|min:0|max:5',
            'comment' => 'nullable|string',
        ]);

        $rating->update($validated);

        return response()->json([
            'message' => 'Rating updated successfully',
            'status' => 200,
            'data' => $rating,
        ], 200);
    }

    public function destroy(string $id)
    {
        $rating = Rating::find($id);
        if (!$rating) {
            return response()->json([
                'message' => 'Rating not found!',
            ], 404);
        }

        // Soft-delete if column exists, otherwise hard delete
        if (Schema::hasColumn('ratings', 'is_deleted')) {
            $rating->is_deleted = 1;
            $rating->save();
        } else {
            $rating->delete();
        }

        return response()->json([
            'message' => 'Rating deleted successfully',
            'status' => 200,
            'data' => $rating,
        ], 200);
    }
}
