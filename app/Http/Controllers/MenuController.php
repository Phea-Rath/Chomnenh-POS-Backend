<?php

namespace App\Http\Controllers;

use App\Models\Menus;
use App\Models\Permission;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        $allMenus = Menus::all();

        foreach ($allMenus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
        }

        $groupedMenus = $allMenus->groupBy('parent_menu')->map(function ($group) {
            return $group->values();
        });

        $newFormat = $allMenus->filter(function ($menu) {
            return is_null($menu->parent_menu) || $menu->parent_menu === '';
        })->map(function ($menu) use ($groupedMenus) {
            return [
                ...$menu->toArray(),
                'menus' => $groupedMenus->get($menu->menu_id, collect())->values(),
            ];
        })->values();

        $orphanMenus = $allMenus->filter(function ($menu) use ($allMenus) {
            if (is_null($menu->parent_menu) || $menu->parent_menu === '') {
                return false;
            }

            return !$allMenus->firstWhere('menu_id', $menu->parent_menu);
        })->map(function ($menu) {
            return [
                ...$menu->toArray(),
                'sub_menus' => [],
            ];
        })->values();

        $newFormat = $newFormat->concat($orphanMenus)->values();

        return response()->json([
            'message' => 'menus get successfully',
            'status' => 200,
            'data' => $newFormat
        ]);
    }

    // Show a single menu
    public function show($id)
    {
        $menu = Menus::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }
        return response()->json([
            'message' => 'menus get successfully',
            'status' => 200,
            'data' => $menu
        ]);
    }

    // Create a new menu
    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_name' => 'required|string',
            'menu_type' => 'required',
            'menu_icon' => '',
            'menu_path' => 'required|string',
            'order_menu' => 'required|integer',
        ]);

        $imageName = null;
        if ($request->hasFile('menu_icon')) {
            $file = $request->file('menu_icon');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
        $menu = Menus::create([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_icon' => $imageName,
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
        ]);
        if (!empty($menu)) {
            Permission::create([
                'user_id' => 1,
                'menu_id' => $menu->menu_id,
            ]);
        }
        return response()->json([
            'message' => 'menus created successfully',
            'status' => 200,
            'data' => $menu
        ]);
    }

    // Update an existing menu
    public function update(Request $request, $id)
    {
        $menu = Menus::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }
        $validated = $request->validate([
            'menu_name' => 'sometimes|string',
            'menu_type' => 'sometimes',
            'menu_icon' => '',
            'menu_path' => 'sometimes|string',
            'order_menu' => 'required|integer',
        ]);
        $imageName = null;
        if ($request->hasFile('menu_icon')) {
            $file = $request->file('menu_icon');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
        if($imageName){
            $menu->update([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_icon' => $imageName,
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
            ]);
        }else{
            $menu->update([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
        ]);
        }

        return response()->json([
            'message' => 'menus updated successfully',
            'status' => 200,
            'data' => $menu
        ]);
    }

    // Delete a menu
    public function destroy($id)
    {
        $menu = Menus::find($id);
        if (!$menu) {
            return response()->json(['message' => 'Menu not found'], 404);
        }
        $menu->delete();
        return response()->json([
            'message' => 'menus deleted successfully',
            'status' => 200,
            'data' => $menu
        ]);
    }


    public function getEMenuByUserId(Request $request)
    {
        $user = $request->user();
        // Get format result path/token/profile_id
        $path = env('EMENU_URL', 'http://www.chomnenhapp.com/');
        $user = $request->user();
        $proId = $user->profile_id;
        $token = $request->bearerToken();

        $url = $path . $token . '/' . $proId;
        return response()->json([
            'message' => 'token get successfully',
            'status' => 200,
            'data' => $url
        ]);
    }
}
