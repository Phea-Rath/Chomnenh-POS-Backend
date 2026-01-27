<?php

namespace App\Http\Controllers;

use App\Models\Brands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\TelegramService;

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
                ->select('brands.*')
                // ->paginate($page);
                ->where('brands.is_deleted', 0)
                ->get();
        } else {
            $brands = DB::table('brands')
                ->join('users', 'brands.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                // ->where('brands.created_by', $uid)
                ->select('brands.*')
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

        $message = "
        🛒 <b>New Order Received</b>

        🏪 <b>Shop:</b> KV9 CAMBODIA
        🆔 <b>Order No:</b> 3840
        📞 <b>Buyer Phone:</b> 069400142
        📦 <b>Recipient Phone:</b> 069400142
        📍 <b>Location:</b> sfsdf
        📌 <b>District:</b> ៧មករា

        📦 Items
        • K White Foam (ហ្វូមលាងមុខជប៉ុន) × 1 = $8.00
        • K white Serum UV ( សេរ៉ូមការពារកំដៅថ្ងៃ) × 1 = $12.00

        💰 Total: $20.00
        ";


        TelegramService::sendMessage($message);
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
            'created_by' => 'required|integer',
        ]);

        // Create the post
        $data = Brands::create([
            'brand_name' => $validated['brand_name'],
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
                ->select('brands.*')
                // ->paginate($page);
                ->where('brands.brand_id', $id)
                ->where('brands.is_deleted', 0)
                ->get();
        } else {
            $brands = DB::table('brands')
                ->join('users', 'brand.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->where('created_by', $uid)
                ->select('brand.*')
                // ->paginate($page);
                ->where('brands.brand_id', $id)
                ->where('brands.is_deleted', 0)
                ->get();
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
