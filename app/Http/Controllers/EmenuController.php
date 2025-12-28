<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmenuController extends Controller
{
    public function show(string $id)
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;

        $profile = Profile::where('id', $id)->where('is_deleted', 0)
            // ->where('created_by', $proId)
            ->first();

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found!',
                'status'  => 404,
            ]);
        }

        if ($profile->image) {
            $profile->image = url('storage/images/' . basename($profile->image));
        }

        return response()->json([
            'message' => 'Profile retrieved successfully!',
            'status'  => 200,
            'data'    => $profile,
        ]);
    }
}
