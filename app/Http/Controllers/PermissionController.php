<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PermissionController extends Controller
{
    // List all permissions
    public function index()
    {
        $user = Auth::user();
        if (!$user || !$user->profile_id) {
            return response()->json([
                'message' => 'User or profile not found',
                'status' => 200,
                'data' => []
            ], 200);
        }
        $proId = $user->profile_id;
        $role = $user->role_id;
        $query = Permission::join('users', 'users.id', '=', 'permission.user_id')
            ->join('profiles', 'profiles.id', '=', 'users.profile_id')
            ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
            // ->where('profile_id', $proId)
            ->select('permission.user_id', 'permission.menu_id', 'menus.menu_name', 'menus.menu_type', 'menus.menu_icon', 'menus.menu_path');
        if ($role === 1) {
            $permissions = $query->get();
        } else if ($role === 2) {
            // filter by profile_id
            $permissions = $query->where('profile_id', $proId)->get();
        } else {
            // default: no result
            $user = [];
        }
        return response()->json([
            'message' => 'permission get successfully',
            'status' => 200,
            'data' => $permissions
        ]);
    }

    // Show permissions for a specific user
    public function show($user_id)
    {
        $permissions = Permission::where('user_id', $user_id)
            ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
            ->select('user_id', 'permission.menu_id', 'menus.menu_name', 'menus.menu_type', 'menus.menu_icon', 'menus.menu_path')
            ->get();
        if ($permissions->isEmpty()) {
            return response()->json([
                'message' => 'No permissions found for this user',
                'status' => 200,
                'data' => [],
            ], 200);
        }
        return response()->json([
            'message' => 'permission show successfully',
            'status' => 200,
            'data' => $permissions
        ]);
    }

    // Assign a menu permission to a user
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'menu_id' => 'required|exists:menus,menu_id',
            'all_menu' => 'array',
            'all_menu.*.user_id' => 'exists:users,id',
            'all_menu.*.menu_id' => 'exists:menus,menu_id',
        ]);
        $allMenu = $validated['all_menu'] ?? [];
        if (empty($allMenu)) {
            $permission = Permission::create($validated);
            return response()->json([
                'message' => 'permission successfully',
                'status' => 200,
                'data' => $permission
            ]);
        } else {
            $deleted = DB::table("permission")->where('user_id', $validated['user_id'])->delete();
            if ($deleted === false) {
                return response()->json([
                    'message' => 'Failed to delete old permissions',
                    'status' => 500,
                    'data' => null
                ], 500);
            }
            $new_menu = [];
            foreach ($allMenu as $item) {
                $new_menu[] = Permission::create([
                    'user_id' => $item['user_id'],
                    'menu_id' => $item['menu_id'],
                ]);
            }
            return response()->json([
                'message' => 'permissions updated successfully',
                'status' => 200,
                'data' => $new_menu
            ]);
        }
    }

    // Remove a menu permission from a user
    public function destroy($user_id, $menu_id)
    {
        if ($menu_id == 0) {
            $deleted = DB::table("permission")
                ->where('user_id', $user_id)
                ->delete();
            if ($deleted === 0) {
                return response()->json([
                    'message' => 'Permission not found',
                    'status' => 404,
                    'data' => null
                ], 404);
            }
            return response()->json([
                'message' => 'unpermission successfully',
                'status' => 200,
                'data' => null
            ]);
        }
        $deleted = DB::table("permission")
            ->where('user_id', $user_id)
            ->where("menu_id", $menu_id)
            ->delete();
        if ($deleted === 0) {
            return response()->json([
                'message' => 'Permission not found',
                'status' => 404,
                'data' => null
            ], 404);
        }
        return response()->json([
            'message' => 'unpermission successfully',
            'status' => 200,
            'data' => null
        ]);
    }
}
