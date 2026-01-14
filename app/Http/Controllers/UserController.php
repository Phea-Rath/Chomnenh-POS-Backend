<?php

namespace App\Http\Controllers;

use App\Models\Users;
use Auth;
use DB;
use Illuminate\Http\Request;
use Storage;

class UserController extends Controller
{
    public function userLogin()
    {
        $user = Auth::user();
        if ($user->image) {
            $filenameOnly = basename($user->image);
            $imageUrl = url('storage/images/' . $filenameOnly);
            $user->image = $imageUrl;
        }
        return response()->json([
            'message' => 'Profiles selected successfully',
            'status' => 200,
            'data' => $user,
        ]);
    }
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $proid = $user->profile_id;
        $role = $user->role_id;
        $users = DB::table('users as u')
            ->leftJoin('users as c', 'u.created_by', '=', 'c.id')   // 👈 self join
            ->where('u.is_deleted', 0)
            ->select(
                'u.*',
                'c.username as created_by_name'
            );
        if ($role === 1) {
            // get all users (no filter)
            $users = $users->whereIn('u.created_by', [0, $uid])->get();
        } elseif ($role === 4 || $role === 3) {
            // filter by profile_id
            $users = $users->where('u.profile_id', $proid)->whereNot('u.id',$user->created_by)->get();
        } else {
            // filter by user id
            $users = $users->where('u.id', $uid)->get();
        }
        if (!$users) {
            return response()->json([
                'message' => 'user not found!',
            ], 404);
        }
        foreach ($users as $item) {
            // $url = asset($item->item_image );
            if ($item->image) {
                $filenameOnly = basename($item->image);
                $imageUrl = url('storage/images/' . $filenameOnly);
                $item->image = $imageUrl;
            }
        }
        return response()->json([
            'message' => 'Profiles selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($users->toArray()),
        ]);
    }
    public function show(string $id)
    {
        $user = DB::table('users as u')
            ->leftJoin('users as c', 'u.created_by', '=', 'c.id')   // 👈 self join
            ->where('u.is_deleted', 0)
            ->select(
                'u.*',
                'c.username as created_by_name'
            )->where('u.id', $id)->first();
        if (!$user) {
            return response()->json([
                'message' => 'user not found!',
            ], 404);
        }
        if ($user->image) {
            $filenameOnly = basename($user->image);
            $imageUrl = url('storage/images/' . $filenameOnly);
            $user->image = $imageUrl;
        }
        return response()->json([
            'message' => 'Profiles show successfully',
            'status' => 200,
            'data' => $user,
        ]);
    }


    public function showByProId(string $id)
    {
        // $user = Auth::user();
        // return  response()->json(Users::latest()->get());
        $users = DB::table('users')
            ->where('is_deleted', 0)->where('profile_id', $id)->get();
        if (count($users) == 0) {
            return response()->json([
                'message' => 'user not found!',
            ], 404);
        }
        foreach($users as $user){
            if ($user->image) {
                $filenameOnly = basename($user->image);
                $imageUrl = url('storage/images/' . $filenameOnly);
                $user->image = $imageUrl;
            }
        }
        return response()->json([
            'message' => 'Profiles selected successfully',
            'status' => 200,
            'data' => $users,
        ]);
    }

    public function update(Request $request, string $id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        $user = Users::find($id);

        if (!$user) {
            return response()->json([
                "message" => "This brand not found!",
            ], 404);
        }

        $fields = $request->validate([
            "username" => "required|string",
            "user_id" => "required|integer",
            "phone_number" => "required|string|unique:user,phone_number",
            "role" => "required|string",
            "status" => "required|integer",
            "created_by" => "required|integer",
            "password" => "required|string",
            'image' => 'nullable|file|mimes:jpeg,png',
        ]);

        // Initialize the image model
        // $users = new Users();

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
                // Return an error response if the file couldn't be stored
                // return response()->json([
                //     'message' => 'Failed to upload image',
                // ], 500);
                $user = Users::where('id', $id)->update([
                    "username" => $fields["username"],
                    "user_id" => $fields["user_id"],
                    "phone_number" => $fields["phone_number"],
                    "role" => $fields["role"],
                    "status" => $fields["status"],
                    "created_by" => $fields["created_by"],
                    "image" => $filename,
                    "password" => bcrypt($fields["password"])
                ]);
            }
        } else {

            $user = Users::where('id', $id)->update([
                "username" => $fields["username"],
                "user_id" => $fields["user_id"],
                "phone_number" => $fields["phone_number"],
                "role" => $fields["role"],
                "status" => $fields["status"],
                "created_by" => $fields["created_by"],
                "image" => null,
                "password" => bcrypt($fields["password"])
            ]);
        }
    }

    public function disabledUser($id){
        $user = Users::find($id);
        if(empty($user)){
            return response()->json([
                'message'=>'User not found!',
                'status'=> 201,
            ],300);
        }
        $user->status = 0;
        $user->save();

        return response()->json([
            'message'=>'User disabled successfully!',
            'status'=> 200,
            'data'=>$user
        ],201);
    }
    public function enabledUser($id){
        $user = Users::find($id);
        if(empty($user)){
            return response()->json([
                'message'=>'User not found!',
                'status'=> 201,
            ],300);
        }
        $user->status = 1;
        $user->save();

        return response()->json([
            'message'=>'User disabled successfully!',
            'status'=> 200,
            'data'=>$user
        ],201);
    }

    public function disabledCompany($id){
        $user = Auth::user();
        $uId = $user->id;
        if($uId != 1){
            return response()->json([
                'message'=>'User cannot enable!',
                'status'=> 201,
            ],300);
        }
        $users = Users::where('profile_id',$id)->get();
        if(empty($user)){
            return response()->json([
                'message'=>'User not found!',
                'status'=> 201,
            ],300);
        }
        foreach($users as $user){
            $user->status = 0;
            $user->save();
        }

        return response()->json([
            'message'=>'User enabled successfully!',
            'status'=> 200,
            'data'=>$users
        ],201);
    }
    public function enabledCompany($id){
        $user = Auth::user();
        $uId = $user->id;
        if($uId != 1){
            return response()->json([
                'message'=>'User cannot enable!',
                'status'=> 201,
            ],300);
        }
        $users = Users::where('profile_id',$id)->get();
        if(empty($user)){
            return response()->json([
                'message'=>'User not found!',
                'status'=> 201,
            ],300);
        }
        foreach($users as $user){
            $user->status = 1;
            $user->save();
        }

        return response()->json([
            'message'=>'User enabled successfully!',
            'status'=> 200,
            'data'=>$users
        ],201);
    }

    public function destroy(string $id)
    {
        $users = Users::find($id);
        if (!$users) {
            return response()->json([
                "message" => "This user not found!",
            ], 404);
        }
        if ($users->image) {
            $imagePath = public_path('storage/images/' . $users->image);
            if (file_exists($imagePath)) {
                unlink($imagePath);
                $users->is_deleted = 1;
                $users->save();
                return response()->json([
                    "message" => "User deleted successfully",
                    "status" => 200,
                    "data" => $users,
                ], 200);
            } else {
                $users->is_deleted = 1;
                $users->save();

                return response()->json([
                    "message" => "User not folder image",
                    "status" => 200,
                    "data" => $users,
                ], 200);
            }
        } else {
            return response()->json([
                "message" => "User not image",
                "status" => 200,
                "data" => $users,
            ], 200);
        }
    }

    public function updateImage(Request $request, string $id)
    {
        $user = Users::find($id);

        if (!$user) {
            return response()->json([
                "message" => "This user not found!",
                "status"  => 404,
            ]);
        }

        $validated = $request->validate([
            'image' => 'required|file|image',
        ]);

        // Delete old image if exists
        if ($user->image && Storage::exists('public/images/' . $user->image)) {
            Storage::delete('public/images/' . $user->image);
        }

        // Upload new image
        $file = $request->file('image');
        $filename = time() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/images', $filename);

        $user->image = $filename;
        $user->save();

        $user->image = url('storage/images/' . $filename);

        return response()->json([
            "message" => "Users image updated successfully",
            "status"  => 200,
            "data"    => $user,
        ]);
    }


    public function updateNumberPhone(Request $request, string $id)
    {
        $user = Users::find($id);
        $validate = $request->validate([
            'phone_number' => 'required|string|max:10'
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'Users not found!',
                'status'  => 404,
            ]);
        }

        $user->phone_number = $validate['phone_number'];
        $user->save();
        return response()->json([
            "message" => "Users number phone updated successfully",
            "status"  => 200,
            "data"    => $user,
        ]);
    }
    public function updateName(Request $request, string $id)
    {
        $user = Users::find($id);
        $validate = $request->validate([
            'user_name' => 'required|string|max:200'
        ]);

        if (!$user) {
            return response()->json([
                'message' => 'Users not found!',
                'status'  => 404,
            ]);
        }

        $user->username = $validate['user_name'];
        $user->save();
        return response()->json([
            "message" => "Users name updated successfully",
            "status"  => 200,
            "data"    => $user,
        ]);
    }
}
