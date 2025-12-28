<?php

namespace App\Http\Controllers;

use App\Models\Menus;
use App\Models\Permission;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'menus get successfully',
            'status' => 200,
            'data' => Menus::all()
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
            'menu_icon' => 'required|string',
            'menu_path' => 'required|string',
        ]);
        $menu = Menus::create($validated);
        if (!empty($menu)) {
            Permission::create([
                'user_id' => 1,
                'menu_id' => $menu->menu_id,
            ]);
        }
        // return response()->json($menu, 201);
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
            'menu_icon' => 'sometimes|string',
            'menu_path' => 'sometimes|string',
        ]);
        $menu->update($validated);
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
}
