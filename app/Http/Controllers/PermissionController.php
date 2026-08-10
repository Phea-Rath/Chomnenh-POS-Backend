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
            ->where('users.id', $user->id)->select('permission.*')->get();
        return response()->json([
            'message' => 'permission get successfully',
            'status' => 200,
            'data' => $query
        ]);
    }

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


    // Show permissions for a specific user
    public function show($user_id)
    {
        $permissions = Permission::where('user_id', $user_id)
            ->join('menus', 'permission.menu_id', '=', 'menus.menu_id')
            ->select(
                'user_id',
                'permission.menu_id',
                'menus.menu_name',
                'menus.menu_name_km',
                'menus.menu_type',
                'menus.menu_icon',
                'menus.menu_path',
                'menus.parent_menu',
                'menus.order_menu',
                'permission.is_view',
                'permission.is_modify',
                'permission.is_drop',
                'permission.is_execute'
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
            ->whereIn('m.only', ['all', 'mobile'])
            ->select(
                'm.menu_id',
                'm.menu_name',
                'm.menu_name_km',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                'p.is_view',
                'p.is_modify',
                'p.is_drop',
                'p.is_execute',
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
                'm.menu_name_km',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                'p.is_view',
                'p.is_modify',
                'p.is_drop',
                'p.is_execute',
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
                'is_view' => true,
                'is_modify' => true,
                'is_drop' => true,
                'is_execute' => true,
            ]);
        }
        return response()->json([
            'message' => 'permissions updated successfully',
            'status' => 200,
            'data' => $new_menu
        ]);

    }
    public function updateAllow(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'menu_id' => 'required|integer|exists:menus,menu_id',
            'is_view' => 'required|boolean',
            'is_modify' => 'required|boolean',
            'is_drop' => 'required|boolean',
            'is_execute' => 'required|boolean',
        ]);

        $deleted = DB::table("permission")->where('user_id', $validated['user_id'])->where('menu_id', $validated['menu_id'])->delete();
        if ($deleted === false) {
            return response()->json([
                'message' => 'Failed to delete old permissions',
                'status' => 500,
                'data' => null
            ], 500);
        }
        $new_menu = Permission::create([
            'user_id' => $validated['user_id'],
            'menu_id' => $validated['menu_id'],
            'is_view' => $validated['is_view'],
            'is_modify' => $validated['is_modify'],
            'is_drop' => $validated['is_drop'],
            'is_execute' => $validated['is_execute'],
        ]);
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
            'menu_name_km' => property_exists($menu, 'menu_name_km') ? $menu->menu_name_km : (isset($menu->menu_name_km) ? $menu->menu_name_km : null),
            'menu_icon' => $menu->menu_icon,
            'menu_path' => $menu->menu_path,
            'parent_menu' => $menu->parent_menu,
            'order_menu' => $menu->order_menu,
            'base_on' => property_exists($menu, 'base_on') ? $menu->base_on : (isset($menu->base_on) ? $menu->base_on : null),
        ];

        // include menu_type only when the item has no parent (i.e. is a root menu)
        if (is_null($menu->parent_menu) || $menu->parent_menu === '') {
            $data['menu_type'] = $menu->menu_type;
        }

        if (isset($menu->active)) {
            $data['active'] = (int) $menu->active;
        }

        $data['is_view'] = isset($menu->is_view) ? (int)$menu->is_view : 0;
        $data['is_modify'] = isset($menu->is_modify) ? (int)$menu->is_modify : 0;
        $data['is_drop'] = isset($menu->is_drop) ? (int)$menu->is_drop : 0;
        $data['is_execute'] = isset($menu->is_execute) ? (int)$menu->is_execute : 0;

        return $data;
    }

    private function normalizeParentKey($parentMenu): string
    {
        if (is_null($parentMenu) || $parentMenu === '') {
            return '__root__';
        }

        return (string) $parentMenu;
    }



    public function getPermissionMenuByCurrentUserV2()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'status' => 401,
                'data' => []
            ], 401);
        }

        $query = DB::table('menus as m')
            ->whereIn('m.only', ['all', 'website'])
            ->where('m.base_on', '!=', 'function');

        if ($user->role_id !== 1) {
            // For non-superadmins, only show menus they actually have permission for
            $query->join('permission as p', function ($join) use ($user) {
                $join->on('m.menu_id', '=', 'p.menu_id')
                    ->where('p.user_id', '=', $user->id);
            });
        } else {
            // Superadmins see all menus, left join to get any overrides but they are always active
            $query->leftJoin('permission as p', function ($join) use ($user) {
                $join->on('m.menu_id', '=', 'p.menu_id')
                    ->where('p.user_id', '=', $user->id);
            });
        }

        $menus = $query->select(
                'm.menu_id',
                'm.menu_name',
                'm.menu_name_km',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                'm.base_on',
                DB::raw($user->role_id === 1 ? 'COALESCE(p.is_view, 1) as is_view' : 'p.is_view'),
                DB::raw($user->role_id === 1 ? 'COALESCE(p.is_modify, 1) as is_modify' : 'p.is_modify'),
                DB::raw($user->role_id === 1 ? 'COALESCE(p.is_drop, 1) as is_drop' : 'p.is_drop'),
                DB::raw($user->role_id === 1 ? 'COALESCE(p.is_execute, 1) as is_execute' : 'p.is_execute'),
                DB::raw($user->role_id === 1 ? '1 as active' : '1 as active')
            )
            ->orderBy('m.order_menu', 'asc')
            ->orderBy('m.menu_id', 'asc')
            ->get();

        $menus = $this->formatPermissionMenusV2($menus);

        return response()->json([
            'message' => 'permission menus get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }


    public function getPermissionMenuByUserV2($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'message' => 'User not authenticated',
                'status' => 401,
                'data' => []
            ], 401);
        }

        $query = DB::table('menus as m')
            ->whereIn('m.only', ['all', 'website']);

        if ($user->role_id !== 1) {
            // An admin can only manage permissions for menus they themselves have access to
            $query->join('permission as ap', function ($join) use ($user) {
                $join->on('m.menu_id', '=', 'ap.menu_id')
                    ->where('ap.user_id', '=', $user->id);
            });
        }

        $menus = $query->leftJoin('permission as p', function ($join) use ($id) {
                $join->on('m.menu_id', '=', 'p.menu_id')
                    ->where('p.user_id', '=', $id);
            })
            ->select(
                'm.menu_id',
                'm.menu_name',
                'm.menu_name_km',
                'm.menu_type',
                'm.menu_icon',
                'm.menu_path',
                'm.parent_menu',
                'm.order_menu',
                'm.base_on',
                'p.is_view',
                'p.is_modify',
                'p.is_drop',
                'p.is_execute',
                DB::raw('CASE WHEN p.menu_id IS NULL THEN 0 ELSE 1 END as active')
            )
            ->orderBy('m.order_menu', 'asc')
            ->orderBy('m.menu_id', 'asc')
            ->get();

        $menus = $this->formatPermissionMenusV2($menus);
        return response()->json([
            'message' => 'permission menus get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }

    private function formatPermissionMenusV2(Collection $permissions): Collection
    {
        foreach ($permissions as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
        }

        $permissions = $permissions->unique('menu_id')->values();

        // Group menus by base_on to handle collections
        $collections = $permissions->groupBy('base_on');

        foreach ($collections as $baseOn => $collection) {
            // Skip menus that don't belong to a specific base_on collection or are 'single'
            if (!$baseOn || $baseOn === 'single') {
                if ($baseOn === 'single') {
                    foreach ($collection as $item) {
                        $item->parent_menu = null;
                    }
                }
                continue;
            }

            // Find the parent of the collection:
            // - Among menus in the collection, menu_name matches base_on (case-insensitive)
            // - OR menu_id matches base_on
            // - OR menu_id is a parent_menu for others in the collection
            $parent = $collection->first(function ($m) use ($baseOn) {
                return strtolower(trim($m->menu_name)) === strtolower(trim($baseOn));
            }) ?: $collection->first(function ($m) use ($baseOn, $collection) {
                return $m->menu_id == $baseOn || $collection->contains('parent_menu', $m->menu_id);
            });

            if ($parent) {
                // If a parent is found in the collection, designate it and its children
                foreach ($collection as $item) {
                    if ($item->menu_id == $parent->menu_id) {
                        // This item is the parent of the collection
                        $item->parent_menu = null;
                    } else {
                        // Child in collection only if base_on equal base_on of parent
                        if ($item->base_on == $parent->base_on) {
                            $item->parent_menu = $parent->menu_id;
                        }
                    }
                }
            } else {
                // If no parent found in current user's set, make all items in this collection roots
                foreach ($collection as $item) {
                    $item->parent_menu = null;
                }
            }
        }

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
}


