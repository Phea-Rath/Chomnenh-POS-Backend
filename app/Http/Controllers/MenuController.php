<?php

namespace App\Http\Controllers;

use App\Models\Menus;
use App\Models\Permission;
use App\Services\PostImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    protected $postImage;
    function __construct(PostImage $postImage)
    {
        $this->postImage = $postImage;
    }
    public function index()
    {
        $allMenus = Menus::orderBy('order_menu', 'asc')->get();

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

    public function getMenuSidebarByCurrentUser(){
        $user = auth()->user();
        $permissions = Permission::where('user_id', $user->id)->pluck('menu_id');
        $menus = Menus::whereIn('menu_type', [1,6])
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus sidebar get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }

    public function getMenuInventoryByCurrentUser(){
        $user = auth()->user();
        $permissions = Permission::where('user_id', $user->id)->pluck('menu_id');
        $menus = Menus::where('menu_type', "=", 5)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus sidebar get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }

    public function getMenuHomeByCurrentUser(){
        $user = auth()->user();
        $permissions = Permission::where('user_id', $user->id)->pluck('menu_id');
        $menus = Menus::where('menu_type', '=', 2)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus home get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuSettingByCurrentUser(){
        $user = auth()->user();
        $permissions = Permission::where('user_id', $user->id)->pluck('menu_id');
        $query = Menus::select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc');

        if($user->id == 1){
            $query->whereIn('menu_type', [0, 3]);
        }else{
            $query->where('menu_type', '=', 3);
        }

        $menus = $query->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus setting get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuReportByCurrentUser(){
        $user = auth()->user();
        $permissions = Permission::where('user_id', $user->id)->pluck('menu_id');
        $menus = Menus::where('menu_type', '=', 4)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus report get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }


    public function getMenuSidebarByUserId($id){
        $permissions = Permission::where('user_id', $id)->pluck('menu_id');
        $menus = Menus::whereIn('menu_type', [1,6])
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus sidebar get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuInventoryByUserId($id){
        $permissions = Permission::where('user_id', $id)->pluck('menu_id');
        $menus = Menus::where('menu_type', '=', 5)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus sidebar get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuHomeByUserId($id){
        $permissions = Permission::where('user_id', $id)->pluck('menu_id');
        $menus = Menus::where('menu_type', '=', 2)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus home get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuSettingByUserId($id){
        $user =
        $permissions = Permission::where('user_id', $id)->pluck('menu_id');
        $query = Menus::select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc');

        if($user->id == 1){
            $query->whereIn('menu_type', [0, 3]);
        }else{
            $query->where('menu_type', '=', 3);
        }

        $menus = $query->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus setting get successfully',
            'status' => 200,
            'data' => $menus
        ]);
    }
    public function getMenuReportByUserId($id){
        $permissions = Permission::where('user_id', $id)->pluck('menu_id');
        $menus = Menus::where('menu_type', '=', 4)
        ->select('menu_id', 'menu_name', 'menu_icon', 'menu_path', 'order_menu')
        ->orderBy('order_menu', 'asc')
        ->get();

        foreach ($menus as $item) {
            if ($item->menu_icon) {
                $filenameOnly = basename($item->menu_icon);
                $item->menu_icon = url('storage/images/' . $filenameOnly);
            }
            $item->active = in_array($item->menu_id, $permissions->toArray()) ? 1 : 0;
        }

        return response()->json([
            'message' => 'menus report get successfully',
            'status' => 200,
            'data' => $menus
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
            'parent_menu' => 'nullable|integer',
        ]);

        $imageName = null;
        if ($request->hasFile('menu_icon')) {
            $file = $request->file('menu_icon');
            $imageName = $this->postImage->uploadSingle($file);
        }
        $menu = Menus::create([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_icon' => $imageName,
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
            'parent_menu' => $validated['parent_menu'],
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
            'parent_menu' => 'nullable|integer',
        ]);
        $imageName = null;
        if ($request->hasFile('menu_icon')) {
            $file = $request->file('menu_icon');

            $imageName = $this->postImage->replaceSingle($menu->menu_icon, $file);
        }
        if($imageName){
            $menu->update([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_icon' => $imageName,
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
            'parent_menu' => $validated['parent_menu'],
            ]);
        }else{
            $menu->update([
            'menu_name' => $validated['menu_name'],
            'menu_type' => $validated['menu_type'],
            'menu_path' => $validated['menu_path'],
            'order_menu' => $validated['order_menu'],
            'parent_menu' => $validated['parent_menu'],
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
        $proId = $user->profile_id;
        $pro = DB::table('profiles')->where('id', $proId)->first();
        $proImage = $pro->image;
         if ($proImage) {
            $filenameOnly = basename($proImage);
            $proImage = url('storage/images/' . $filenameOnly);
        }
        // Get format result path/token/profile_id
        $path = env('EMENU_URL', 'http://www.chomnenhapp.com/');
        $user = $request->user();
        $proId = $user->profile_id;
        $token = $request->bearerToken();

        $url = $path . $token . '/' . 'order-now/'  . $proId;
        return response()->json([
            'message' => 'e-menu get successfully',
            'status' => 200,
            'data' => [
                'url' => $url,
                'image'=> $proImage
            ]
        ]);
    }
}
