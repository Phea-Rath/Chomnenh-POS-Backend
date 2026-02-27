<?php

namespace App\Http\Controllers;

use App\Models\Brands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\TelegramService;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role_id;
        $proId = $user->profile_id;
        // $page = 2;
        if ($role == 1) {
            $brands = DB::table('brands')
                ->join('users', 'brands.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('users.username as created_by_name', 'brands.*')
                // ->paginate($page);
                ->where('brands.is_deleted', 0)
                ->get();
        } else {
            $brands = DB::table('brands')
                ->join('users', 'brands.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                // ->where('brands.created_by', $uid)
                ->select('users.username as created_by_name', 'brands.*')
                // ->paginate($page);
                ->where('brands.is_deleted', 0)
                ->get();
        }
        if (count($brands) == 0) {
            return response()->json([
                'message' => 'Brands not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $brands
            ]);
        }

        return response()->json([
            'message' => 'Brands selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($brands->toArray()),
        ]);

    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'brand_name' => 'required|string|max:255',
        ]);

        $brandName = Str::lower(trim($validated['brand_name']));
        $exists = Brands::where('is_deleted', 0)
            ->whereRaw('LOWER(TRIM(brand_name)) = ?', [$brandName])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Brand name already exists!',
                'status' => 409,
            ], 409);
        }

        $data = Brands::create([
            'brand_name' => $brandName,
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'Brand created successfully!',
            'status' => 200,
            'data' => $data,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role_id;
        $proId = $user->profile_id;
        // $page = 2;
        if ($role == 1) {
            $brands = DB::table('brands')
                ->join('users', 'brands.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('users.username as created_by_name','brands.*')
                // ->paginate($page);
                ->where('brands.brand_id', $id)
                ->where('brands.is_deleted', 0)
                ->first();
        } else {
            $brands = DB::table('brands')
                ->join('users', 'brands.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                // ->where('brands.created_by', $uid)
                ->select('users.username as created_by_name','brands.*')
                // ->paginate($page);
                ->where('brands.brand_id', $id)
                ->where('brands.is_deleted', 0)
                ->first();
        }
        if (!$brands) {
            return response()->json([
                'message' => 'Brand not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Brand show successfully!',
            'status' => 200,
            'data' => $brands,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $brands = Brands::find($id);

        if (!$brands) {
            return response()->json([
                "message" => "This scale not found!",
            ], 404);
        }

        $validated = $request->validate([
            'brand_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        $brands->update($validated);

        return response()->json([
            "message" => "Brand updated successfully",
            "status" => 200,
            "data" => $brands,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $brands = Brands::find($id);
        if (!$brands) {
            return response()->json([
                "message" => "This brand not found!",
            ], 404);
        }

        $brands->is_deleted = 1;
        $brands->save();
        return response()->json([
            "message" => "Brand deleted successfully",
            "status" => 200,
            "data" => $brands,
        ], 200);
    }
}
