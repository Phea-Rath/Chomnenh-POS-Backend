<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class PermissionController extends Controller
{
    // List all permissions
    // public function index()
    // {
    //     $user = Auth::user();
    //     if (!$user || !$user->profile_id) {
    //         return response()->json([
    //             'message' => 'User or profile not found',
    //             'status' => 200,
    //             'data' => []
    //         ], 200);
    //     }
    //     $proId = $user->profile_id;
    //     $role = $user->role_id;
    //     $permissions = collect();
    //     $query = Permission::join('users', 'users.id', '=', 'permission.user_id')
    //         ->join('profiles', 'profiles.id', '=', 'users.profile_id')
    //         ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
    //         // ->where('profile_id', $proId)
    //         ->select(
    //             'permission.user_id',
    //             'permission.menu_id',
    //             'menus.menu_name',
    //             'menus.menu_type',
    //             'menus.menu_icon',
    //             'menus.menu_path',
    //             'menus.parent_menu',
    //             'menus.order_menu'
    //         );
    //     if ($role === 1) {
    //         $permissions = $query->get();
    //     } else if ($role === 3) {
    //         // filter by profile_id
    //         $permissions = $query->where('profile_id', $proId)->get();
    //     } else {
    //         // default: no result
    //         $permissions = collect();
    //     }

    //     $permissions = $this->formatPermissionMenus($permissions);

    //     return response()->json([
    //         'message' => 'permission get successfully',
    //         'status' => 200,
    //         'data' => $permissions
    //     ]);
    // }

    // // Show permissions for a specific user
    // public function show($user_id)
    // {
    //     $permissions = Permission::where('user_id', $user_id)
    //         ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
    //         ->select(
    //             'user_id',
    //             'permission.menu_id',
    //             'menus.menu_name',
    //             'menus.menu_type',
    //             'menus.menu_icon',
    //             'menus.menu_path',
    //             'menus.parent_menu',
    //             'menus.order_menu'
    //         )
    //         ->get();

    //     $permissions = $this->formatPermissionMenus($permissions);

    //     if ($permissions->isEmpty()) {
    //         return response()->json([
    //             'message' => 'No permissions found for this user',
    //             'status' => 200,
    //             'data' => [],
    //         ], 200);
    //     }
    //     return response()->json([
    //         'message' => 'permission show successfully',
    //         'status' => 200,
    //         'data' => $permissions
    //     ]);
    // }

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
        $permissions = collect();
        $query = Permission::join('users', 'users.id', '=', 'permission.user_id')
            ->join('profiles', 'profiles.id', '=', 'users.profile_id')
            ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
            // ->where('profile_id', $proId)
            ->select(
                'permission.user_id',
                'permission.menu_id',
                'menus.menu_name',
                'menus.menu_type',
                'menus.menu_icon',
                'menus.menu_path',
                'menus.parent_menu',
                'menus.order_menu'
            )
            ->orderBy('menus.order_menu', 'asc');
        if ($role === 1) {
            $permissions = $query->get();
        } else if ($role === 3) {
            // filter by profile_id
            $permissions = $query->where('profile_id', $proId)->get();
        } else {
            // default: no result
            $permissions = collect();
        }

        $permissions = $this->formatPermissionMenus($permissions);

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
            ->select(
                'user_id',
                'permission.menu_id',
                'menus.menu_name',
                'menus.menu_type',
                'menus.menu_icon',
                'menus.menu_path',
                'menus.parent_menu',
                'menus.order_menu'
            )
            ->orderBy('menus.order_menu', 'asc')
            ->get();

        $permissions = $this->formatPermissionMenus($permissions);

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

    public function getPermissionMenuByCurrentUser()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'status' => 401,
                'data' => []
            ], 401);
        }

        $menus = DB::table('menus as m')
            ->leftJoin('permission as p', function ($join) use ($user) {
                $join->on('m.menu_id', '=', 'p.menu_id')
                    ->where('p.user_id', '=', $user->id);
            })
            ->select(
                'm.menu_id',
                'm.menu_name',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                DB::raw('CASE WHEN p.menu_id IS NULL THEN 0 ELSE 1 END as active')
            )
            ->orderBy('m.order_menu', 'asc')
            ->orderBy('m.menu_id', 'asc')
            ->get();

        $menus = $this->formatPermissionMenus($menus);

        return response()->json([
            'message' => 'permission menus get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }


    public function getPermissionMenuByUser($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'status' => 401,
                'data' => []
            ], 401);
        }

        $menus = DB::table('menus as m')
            ->leftJoin('permission as p', function ($join) use ($id) {
                $join->on('m.menu_id', '=', 'p.menu_id')
                    ->where('p.user_id', '=', $id);
            })
            ->select(
                'm.menu_id',
                'm.menu_name',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                DB::raw('CASE WHEN p.menu_id IS NULL THEN 0 ELSE 1 END as active')
            )
            ->orderBy('m.order_menu', 'asc')
            ->orderBy('m.menu_id', 'asc')
            ->get();

        $menus = $this->formatPermissionMenus($menus);

        return response()->json([
            'message' => 'permission menus get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }

    // Assign a menu permission to a user
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'menu_ids' => 'required|array',
        ]);
        $allMenu = $validated['menu_ids'] ?? [];

        $deleted = DB::table("permission")->where('user_id', $validated['user_id'])->whereIn('menu_id', $allMenu)->delete();
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
                'user_id' => $validated['user_id'],
                'menu_id' => $item,
            ]);
        }
        return response()->json([
            'message' => 'permissions updated successfully',
            'status' => 200,
            'data' => $new_menu
        ]);

    }

    // Remove a menu permission from a user
    public function destroy(Request $request, $user_id)
    {
        $menus = $request->input();
        if (empty($menus)) {
            return response()->json([
                'message' => 'No menu IDs provided',
                'status' => 400,
                'data' => null
            ], 400);
        }

        $deleted = DB::table("permission")
            ->where('user_id', $user_id)
            ->whereIn('menu_id', $menus)
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
            'data' => $deleted
        ]);
    }

    private function formatPermissionMenus(Collection $permissions): Collection
    {
        foreach ($permissions as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
        }

        $permissions = $permissions->unique('menu_id')->values();

        $menusById = $permissions->keyBy('menu_id');
        $childrenByParent = $permissions->groupBy(function ($menu) {
            return $this->normalizeParentKey($menu->parent_menu);
        });

        $roots = $permissions->filter(function ($menu) use ($menusById) {
            if ($this->normalizeParentKey($menu->parent_menu) === '__root__') {
                return true;
            }

            return !$menusById->has((string) $menu->parent_menu) && !$menusById->has((int) $menu->parent_menu);
        })->sortBy([
            ['order_menu', 'asc'],
            ['menu_id', 'asc'],
        ])->values();

        return $roots->map(function ($menu) use ($childrenByParent) {
            return $this->buildPermissionMenuNode($menu, $childrenByParent, []);
        })->values();
    }

    private function buildPermissionMenuNode($menu, Collection $childrenByParent, array $visited): array
    {
        $menuId = (string) $menu->menu_id;
        if (in_array($menuId, $visited, true)) {
            return [
                ...$this->serializePermissionMenu($menu),
                'menus' => [],
            ];
        }

        $visited[] = $menuId;
        $children = $childrenByParent->get($menuId, collect())->sortBy([
            ['order_menu', 'asc'],
            ['menu_id', 'asc'],
        ])->values()->map(function ($child) use ($childrenByParent, $visited) {
            return $this->buildPermissionMenuNode($child, $childrenByParent, $visited);
        })->all();

        return [
            ...$this->serializePermissionMenu($menu),
            'menus' => $children,
        ];
    }

    private function serializePermissionMenu($menu): array
    {
        // Always include the basics; menu_type should only be shown for top‑level items
        $data = [
            'menu_id' => $menu->menu_id,
            'menu_name' => $menu->menu_name,
            'menu_icon' => $menu->menu_icon,
            'menu_path' => $menu->menu_path,
            'parent_menu' => $menu->parent_menu,
            'order_menu' => $menu->order_menu,
        ];

        // include menu_type only when the item has no parent (i.e. is a root menu)
        if (is_null($menu->parent_menu) || $menu->parent_menu === '') {
            $data['menu_type'] = $menu->menu_type;
        }

        if (isset($menu->active)) {
            $data['active'] = (int) $menu->active;
        }

        return $data;
    }

    private function normalizeParentKey($parentMenu): string
    {
        if (is_null($parentMenu) || $parentMenu === '') {
            return '__root__';
        }

        return (string) $parentMenu;
    }
}


