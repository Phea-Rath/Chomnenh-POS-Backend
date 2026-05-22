<?php

namespace App\Http\Controllers;

use App\Models\Deliver;
use App\Models\ExchangeRate;
use App\Models\Permission;
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
    public function handleTelegramLogin(Request $request)
    {
        $authData = $request->all();
        $existingUser = Users::where('telegram_id', $authData['id'])->first();
        if($existingUser) {
            $token = $existingUser->createToken('auth_token')->plainTextToken;
            return response()->json([
                'success' => true,
                'access_token' => $token,
                'message' => 'User already exists. Please log in with your credentials.',
                'user' => $existingUser,
            ], 200);
            // User already exists, update their information if needed
        }

        // 1. Extract the verification signature hash and isolate it from the data fields
        $checkHash = $authData['hash'] ?? '';
        unset($authData['hash']);

        // 2. Sort all remaining incoming parameters alphabetically
        ksort($authData);

        // 3. Map values to a "key=value" string format separated by new-lines
        $dataCheckArr = [];
        foreach ($authData as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // 4. Create the signature comparison key using your Bot Token
        $secretKey = hash('sha256', env('TELEGRAM_BOT_TOKEN', '8951212509:AAEdRsV1fiJhZNSJBFT5MAL8FdgY3GKOQLQ'), true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // 5. Securely compare hashes & ensure request isn't stale (older than 24 hours)
        if (!hash_equals($hash, $checkHash) || (time() - $authData['auth_date'] > 86400)) {
            return response()->json([
                'success' => false,
                'message' => 'Security check failed. Data tampering detected or request expired.'
            ], 403);
        }

        $profile = Profile::create([
            "profile_name" => trim(($authData['first_name'] ?? '') . ' ' . ($authData['last_name'] ?? '')),
            "telephone" => null,
            "start_date" => now()->format('Y-m-d'),
            "term" => 1,
            "end_date" => now()->addMonth()->format('Y-m-d'),
            'created_by' => 1,
            'image' => null,
        ]);
        ExchangeRate::create([
            'profile_id' => Profile::max('id')
        ]);



        // 6. Valid signature! Find or create the user record by their unique Telegram ID
        $user = Users::firstOrCreate(
            ['telegram_id' => $authData['id']],
            [
                'profile_id' => $profile->id,
                'role_id' => 3,
                'role' => 'admin',
                'created_by' => 1,
                'username' => $authData['username'] ?? trim(($authData['first_name'] ?? '') . ' ' . ($authData['last_name'] ?? '')),
                'email' => $authData['id'] . '@telegram.user', // Dummy email fallback
                'password' => bcrypt(str()->random(24)),
            ]
        );

        $menuCount = DB::table('menus')->count();
        foreach (range(1, $menuCount) as $menuId) {
            if($menuId == 4) continue; // Skip menu_id 4
            Permission::create([
                'user'=>$user->id,
                'menu_id'=>$menuId
            ]);
        }

        // 7. Issue an API authentication token (Sanctum) to pass back to your React app
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user
        ]);
    }


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

                if($fields['role_id']==3){
                    $menuCount = DB::table('menus')->count();
                    foreach (range(1, $menuCount) as $menuId) {
                        if($menuId == 4) continue; // Skip menu_id 4
                        Permission::create([
                            'user'=>$user->id,
                            'menu_id'=>$menuId
                        ]);
                    }
                }

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
