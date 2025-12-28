<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;

        $profiles = DB::table('profiles')
            ->where('is_deleted', 0)
            ->where('id', $proId)
            ->get();

        foreach ($profiles as $item) {
            if ($item->image) {
                $filenameOnly = basename($item->image);
                $item->image = url('storage/images/' . $filenameOnly);
            }
        }

        if ($profiles->isEmpty()) {
            return response()->json([
                'message' => 'Profiles not found!',
                'status' => 404,
                'data' => $profiles,
            ]);
        }

        return response()->json([
            'message' => 'Profiles selected successfully',
            'status' => 200,
            'data' => array_reverse($profiles->toArray()),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;

        $validated = $request->validate([
            "profile_name" => "required|string",
            "telephone"    => "required|string",
            "start_date"   => "required|date", 
            "term"        => "required|integer",
            'image'       => 'sometimes|file|image',
        ]);

        $date = new DateTime($validated["start_date"]);
        $date->modify("+{$validated["term"]} months");
        $endDate = $date->format('Y-m-d');

        $imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }

        $data = Profile::create([
            "profile_name" => $validated["profile_name"],
            "telephone"    => $validated["telephone"],
            "start_date"   => $validated["start_date"],
            "term"         => $validated["term"],
            "end_date"     => $endDate,
            'created_by'   => $uid,
            'image'        => $imageName,
        ]);

        return response()->json([
            'message' => 'Profile created successfully!',
            'status'  => 201,
            'data'    => $data,
        ]);
    }

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

    public function updateImage(Request $request, string $id)
    {
        $profile = Profile::find($id);

        if (!$profile) {
            return response()->json([
                "message" => "This profile not found!",
                "status"  => 404,
            ]);
        }

        $validated = $request->validate([
            'image' => 'required|file|image',
        ]);

        // Delete old image if exists
        if ($profile->image && Storage::exists('public/images/' . $profile->image)) {
            Storage::delete('public/images/' . $profile->image);
        }

        // Upload new image
        $file = $request->file('image');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/images', $filename);

        $profile->image = $filename;
        $profile->save();

        $profile->image = url('storage/images/' . $filename);

        return response()->json([
            "message" => "Profile image updated successfully",
            "status"  => 200,
            "data"    => $profile,
        ]);
    }

    public function destroy(string $id)
    {
        $profile = Profile::find($id);

        if (!$profile) {
            return response()->json([
                "message" => "This profile not found!",
                "status"  => 404,
            ]);
        }

        // Delete profile image if exists
        if ($profile->image && Storage::exists('public/images/' . $profile->image)) {
            Storage::delete('public/images/' . $profile->image);
        }

        $profile->is_deleted = 1;
        $profile->save();

        return response()->json([
            "message" => "Profile deleted successfully",
            "status"  => 200,
            "data"    => $profile,
        ]);
    }
    public function updateNumberPhone(Request $request, string $id)
    {
        $profile = Profile::find($id);
        $validate = $request->validate([
            'number_phone' => 'required|string|max:10'
        ]);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found!',
                'status'  => 404,
            ]);
        }

        $profile->telephone = $validate['number_phone'];
        $profile->save();
        return response()->json([
            "message" => "Profile number phone updated successfully",
            "status"  => 200,
            "data"    => $profile,
        ]);
    }
    public function updateName(Request $request, string $id)
    {
        $profile = Profile::find($id);
        $validate = $request->validate([
            'profile_name' => 'required|string|max:200'
        ]);

        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found!',
                'status'  => 404,
            ]);
        }

        $profile->profile_name = $validate['profile_name'];
        $profile->save();
        return response()->json([
            "message" => "Profile name updated successfully",
            "status"  => 200,
            "data"    => $profile,
        ]);
    }
}
