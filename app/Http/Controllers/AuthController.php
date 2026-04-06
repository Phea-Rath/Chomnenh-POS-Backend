<?php

namespace App\Http\Controllers;

use App\Models\Deliver;
use App\Models\ExchangeRate;
use App\Models\Profile;
use App\Models\Users;
use DateTime;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            "phone_number" => "required",
            "password" => "required",
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $fields = $validator->validated();
        $loginAt = now()->format('Y-m-d');

        $user = Users::where("phone_number", $fields["phone_number"])->first();

        if (!$user) {
            return response([
                "message" => "Phone number not found!",
            ], 404);
        }

        if (!Hash::check($fields['password'], $user->password)) {
            return response([
                "message" => "Incorrect password!",
            ], 404);
        }

        if ($user->status == 0) {
            return response([
                "message" => "User disabled!",
            ], 404);
        }

        $user->update([
            'login_at' => $loginAt
        ]);

        $token = $user->createToken("remember_token")->plainTextToken;

        $respones = [
            'user' => $user,
            'token' => $token,
            'message' => 'Login successful',
            'status' => 200
        ];
        return response($respones, 200);
    }

    public function logout(Request $request)
    {
        // Delete only the token used for the current request
        $request->user()->tokens()->delete();

        return response([
            "message"=> "Logged out successfully",
            "status"=>200
        ], 200);
    }




    public function register(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
        $uid = $user->id;
        $proId = $user->profile_id;
        $fields = $request->validate([
            "username" => "required|string",
            "phone_number" => "required|string|unique:users,phone_number",
            "role_id" => "required|integer",
            "role" => "required|string",
            "status" => "required|integer",
            "password" => "required|string",
            "start_date" => "date|nullable",
            "term" => "integer|nullable",
            'image' => 'nullable|file|mimes:jpeg,png,jpg,svg|max:2048',
        ]);



        if ($request->hasFile('image')) {
            // Process the uploaded file
            $file = $request->file('image');

            // Generate the filename with a unique timestamp
            $filename = time() . '.' . $file->getClientOriginalExtension();

            // Ensure the storage directory exists
            $directory = 'public/images';

            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Store the file in the correct directory using the Storage facade
            $path = $file->storeAs($directory, $filename);

            // Check if the file was successfully stored
            if (!$path) {
                return response()->json([
                    'message' => 'Failed to upload image',
                ], 500);
            } else {
                if (!empty($fields['start_date']) || !empty($fields["term"])) {
                    $date = new DateTime($fields["start_date"]);
                    $date->modify("+{$fields["term"]} months");

                    $endDate = $date->format('Y-m-d');
                    $data = Profile::create([
                        "profile_name" => $fields["username"],
                        "telephone" => $fields["phone_number"],
                        "start_date" => $fields["start_date"],
                        "term" => $fields["term"],
                        "end_date" => $endDate,
                        'created_by' => $uid,
                        'image' => $filename,
                    ]);

                    ExchangeRate::create([
                        'profile_id' => Profile::max('id')
                    ]);
                }

                $user = Users::create([
                    "username" => $fields["username"],
                    "profile_id" => $data->id ?? $proId,
                    "phone_number" => $fields["phone_number"],
                    "role_id" => $fields["role_id"],
                    "role" => $fields["role"],
                    "status" => $fields["status"],
                    "created_by" => $uid,
                    "image" => $filename,
                    "password" => bcrypt($fields["password"])
                ]);

                if($fields["role_id"] == 5){
                    $user = Deliver::create([
                        "deliver_name" => $fields["username"],
                        "created_by" => $uid,
                        "image" => $filename
                    ]);
                }
            }
        } else {

            if (!empty($fields['start_date']) || !empty($fields["term"])) {
                $date = new DateTime($fields["start_date"]);
                $date->modify("+{$fields["term"]} months");

                $endDate = $date->format('Y-m-d');
                $data = Profile::create([
                    "profile_name" => $fields["username"],
                    "telephone" => $fields["phone_number"],
                    "start_date" => $fields["start_date"],
                    "term" => $fields["term"],
                    "end_date" => $endDate,
                    'created_by' => $uid,
                    'image' => null,
                ]);
                ExchangeRate::create([
                    'profile_id' => Profile::max('id')
                ]);
            }


            $user = Users::create([
                "username" => $fields["username"],
                "profile_id" => $data->id ?? $proId,
                "phone_number" => $fields["phone_number"],
                "role_id" => $fields["role_id"],
                "role" => $fields["role"],
                "status" => 1,
                "created_by" => $uid,
                "image" => null,
                "password" => bcrypt($fields["password"])
            ]);

            if($fields["role_id"] == 5){
                $user = Deliver::create([
                    "deliver_name" => $fields["username"],
                    "created_by" => $uid,
                    "image" => null
                ]);
            }
        }



        return response()->json([
            'message' => 'Profile created successfully!',
            'status' => 200,
            'profile' => $data ?? [],
            'users' => $user
        ], 201);
    }

    public function guest(string $phone_number)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }
        $uid = $user->id;
        $proId = $user->profile_id;
        $loginAt = now()->format('Y-m-d');

        $user = Users::where('phone_number',$phone_number)->first();

        if(!$user){
            $user = Users::create([
                "username" => 'guest',
                "profile_id" => $proId,
                "phone_number" => $phone_number,
                "role_id" => 2,
                "role" => 'guest',
                "status" => 1,
                "created_by" => $uid,
                "image" => null,
                "password" => bcrypt('guest')
            ]);
        }

        $user->update([
            'login_at' => $loginAt,
            "profile_id" => $proId
        ]);

        $token = $user->createToken("remember_token")->plainTextToken;

        $respones = [
            'user' => $user,
            'token' => $token
        ];
        return response($respones, 200);

    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
            'new_password' => 'required|string|min:6',
        ]);

        $phoneNumber = $request->phone_number;
        $newPassword = $request->new_password;

        $user = Users::where('phone_number', $phoneNumber)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->password = bcrypt($newPassword);
        $user->save();

        return response()->json([
            'message' => 'Password updated successfully',
            'status' => 200
        ], 200);
    }
}
