<?php

namespace App\Http\Controllers;

use App\Models\Suppliers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role;
        $proId = $user->profile_id;

        if ($role == 'admin') {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('suppliers.*')
                ->where('suppliers.is_deleted', 0)
                ->get();
        } else {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('suppliers.*')
                ->where('suppliers.is_deleted', 0)
                ->get();
        }

        if (count($suppliers) == 0) {
            return response()->json([
                'message' => 'Suppliers not found!',
                'status' => 404,
                'data' => $suppliers
            ]);
        }
        foreach ($suppliers as $item) {
            if ($item->image) {
                $filenameOnly = basename($item->image);
                $item->image = url('storage/images/' . $filenameOnly);
            }
        }
        return response()->json([
            'message' => 'Suppliers selected successfully',
            'status' => 200,
            'data' => array_reverse($suppliers->toArray()),
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;

        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'required|string|max:500',
            'communes' => 'required|string|max:500',
            'districts' => 'required|string|max:500',
            'provinces' => 'required|string|max:500',
            'villages' => 'required|string|max:500',
            'commune_id' => 'required|integer',
            'district_id' => 'required|integer',
            'province_id' => 'required|integer',
            'village_id' => 'required|integer',
            'supplier_tel' => 'nullable|string|max:20',
            'supplier_email' => 'nullable|email|max:255',
            'image'       => '',
        ]);

        $imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
        $data = Suppliers::create([
            'supplier_name' => $validated['supplier_name'],
            'supplier_address' => $validated['supplier_address'],
            'communes' => $validated['communes'],
            'districts' => $validated['districts'],
            'provinces' => $validated['provinces'],
            'villages' => $validated['villages'],
            'commune_id' => $validated['commune_id'],
            'district_id' => $validated['district_id'],
            'province_id' => $validated['province_id'],
            'village_id' => $validated['village_id'],
            'supplier_tel' => $validated['supplier_tel'],
            'supplier_email' => $validated['supplier_email'],
            'created_by' => $uid,
            'image'        => $imageName,
        ]);

        return response()->json([
            'message' => 'Supplier created successfully!',
            'status' => 200,
            'data' => $data,
        ], 201);
    }

    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role;
        $proId = $user->profile_id;

        if ($role == 'admin') {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('suppliers.*')
                ->where('suppliers.supplier_id', $id)
                ->where('suppliers.is_deleted', 0)
                ->first();
        } else {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->where('suppliers.created_by', $uid)
                ->select('suppliers.*')
                ->where('suppliers.supplier_id', $id)
                ->where('suppliers.is_deleted', 0)
                ->first();
        }

        if (!$suppliers) {
            return response()->json([
                'message' => 'Supplier not found!',
                'status' => 404,
            ]);
        }
        if ($suppliers->image) {
            $suppliers->image = url('storage/images/' . basename($suppliers->image));
        }

        return response()->json([
            'message' => 'Supplier retrieved successfully!',
            'status' => 200,
            'data' => $suppliers,
        ], 200);
    }

    public function update(Request $request, string $id)
    {
        $supplier = Suppliers::find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier not found!',
                'status' => 404,
            ]);
        }

        $validated = $request->validate([
            'supplier_name' => 'required|string|max:255',
            'supplier_address' => 'required|string|max:500',
            'communes' => 'required|string|max:500',
            'districts' => 'required|string|max:500',
            'provinces' => 'required|string|max:500',
            'villages' => 'required|string|max:500',
            'commune_id' => 'required|integer',
            'district_id' => 'required|integer',
            'province_id' => 'required|integer',
            'village_id' => 'required|integer',
            'supplier_tel' => 'nullable|string|max:20',
            'supplier_email' => 'nullable|email|max:255',
            'image'       => '',
        ]);
        $imageName = null;
        if ($request->hasFile('image')&&!empty($validated["image"])) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }


        if($imageName){
          $supplier->update([
            'supplier_name' => $validated['supplier_name'],
            'supplier_address' => $validated['supplier_address'],
            'communes' => $validated['communes'],
            'districts' => $validated['districts'],
            'provinces' => $validated['provinces'],
            'villages' => $validated['villages'],
            'commune_id' => $validated['commune_id'],
            'district_id' => $validated['district_id'],
            'province_id' => $validated['province_id'],
            'village_id' => $validated['village_id'],
            'supplier_tel' => $validated['supplier_tel'],
            'supplier_email' => $validated['supplier_email'],
            'image' => $imageName,
        ]);
        }else{
            $supplier->update([
            'supplier_name' => $validated['supplier_name'],
            'supplier_address' => $validated['supplier_address'],
            'communes' => $validated['communes'],
            'districts' => $validated['districts'],
            'provinces' => $validated['provinces'],
            'villages' => $validated['villages'],
            'commune_id' => $validated['commune_id'],
            'district_id' => $validated['district_id'],
            'province_id' => $validated['province_id'],
            'village_id' => $validated['village_id'],
            'supplier_tel' => $validated['supplier_tel'],
            'supplier_email' => $validated['supplier_email'],
        ]);
        }

        return response()->json([
            'message' => 'Supplier updated successfully',
            'status' => 200,
            'data' => $supplier,
        ], 200);
    }

    public function destroy(string $id)
    {
        $supplier = Suppliers::find($id);

        if (!$supplier) {
            return response()->json([
                'message' => 'Supplier not found!',
                'status' => 404,
            ]);
        }

        $supplier->is_deleted = 1;
        $supplier->save();

        return response()->json([
            'message' => 'Supplier deleted successfully',
            'status' => 200,
            'data' => $supplier,
        ], 200);
    }
}
