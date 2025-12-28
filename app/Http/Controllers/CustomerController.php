<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Customers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CustomerController extends Controller
{
	public function index()
	{
		$user = Auth::user();
		$uid = $user->id;
		$role = $user->role_id;
		$proId = $user->profile_id;
		if ($role == 1) {
			$customers = DB::table('customers')
				->join('users', 'customers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->where('customers.customer_id','!=',1)
				->select('customers.*')
				->where('customers.is_deleted', 0)
				->get();
		} else {
			$customers = DB::table('customers')
				->join('users', 'customers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->where('customers.customer_id','!=',1)
				->select('customers.*')
				->where('customers.is_deleted', 0)
				->get();
		}
		if (count($customers) == 0) {
			return response()->json([
				'message' => 'Customers not found!',
				'status' => 404,
				'data' => $customers
			]);
		}

		foreach ($customers as $item) {
            if ($item->image) {
                $filenameOnly = basename($item->image);
                $item->image = url('storage/images/' . $filenameOnly);
            }
        }
		return response()->json([
			'message' => 'Customers selected successfully',
			'status' => 200,
			'data' => array_reverse($customers->toArray()),
		]);
	}

	public function store(Request $request)
	{
		$user = Auth::user();
		$uid = $user->id;
		$validated = $request->validate([
			'customer_name' => 'required|string|max:255',
			'customer_email' => 'nullable|string|max:255',
			'customer_tel' => 'nullable|string|max:255',
			'customer_address' => 'nullable|string|max:255',
            'communes' => 'required|string|max:500',
            'districts' => 'required|string|max:500',
            'provinces' => 'required|string|max:500',
            'villages' => 'required|string|max:500',
            'commune_id' => 'required|integer',
            'district_id' => 'required|integer',
            'province_id' => 'required|integer',
            'village_id' => 'required|integer',
            'image'       => '',
		]);
		$imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
		$data = Customers::create([
			'customer_name' => $validated['customer_name'],
			'customer_email' => $validated['customer_email'] ?? null,
			'customer_tel' => $validated['customer_tel'] ?? null,
			'customer_address' => $validated['customer_address'] ?? null,
            'communes' => $validated['communes'],
            'districts' => $validated['districts'],
            'provinces' => $validated['provinces'],
            'villages' => $validated['villages'],
            'commune_id' => $validated['commune_id'],
            'district_id' => $validated['district_id'],
            'province_id' => $validated['province_id'],
            'village_id' => $validated['village_id'],
			'created_by' => $uid,
            'image'        => $imageName,
		]);
		return response()->json([
			'message' => 'Customer created successfully!',
			'status' => 200,
			'data' => $data,
		], 201);
	}

	public function show(string $id)
	{
		$user = Auth::user();
		$uid = $user->id;
		$role = $user->role_id;
		$proId = $user->profile_id;
		if ($role == 1) {
			$customers = DB::table('customers')
				->join('users', 'customers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->select('customers.*')
				->where('customers.customer_id', $id)
				->where('customers.is_deleted', 0)
				->get();
		} else {
			$customers = DB::table('customers')
				->join('users', 'customers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->where('customers.created_by', $uid)
				->select('customers.*')
				->where('customers.customer_id', $id)
				->where('customers.is_deleted', 0)
				->get();
		}
		if (!$customers) {
			return response()->json([
				'message' => 'Customer not found!',
			], 404);
		}

        if ($customers->image) {
            $customers->image = url('storage/images/' . basename($customers->image));
        }
		return response()->json([
			'message' => 'Customer show successfully!',
			'status' => 200,
			'data' => $customers,
		], 201);
	}

	public function update(Request $request, string $id)
	{
		$customers = Customers::find($id);
		if (!$customers) {
			return response()->json([
				"message" => "This customer not found!",
			], 404);
		}
		$validated = $request->validate([
			'customer_name' => 'required|string|max:255',
			'customer_email' => 'nullable|string|max:255',
			'customer_tel' => 'nullable|string|max:255',
			'customer_address' => 'nullable|string|max:255',
            'communes' => 'required|string|max:500',
            'districts' => 'required|string|max:500',
            'provinces' => 'required|string|max:500',
            'villages' => 'required|string|max:500',
            'commune_id' => 'required|integer',
            'district_id' => 'required|integer',
            'province_id' => 'required|integer',
            'village_id' => 'required|integer',
            'image'       => '',
		]);
		$imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
		if($imageName){
		$customers->update([
			'customer_name' => $validated['customer_name'],
			'customer_email' => $validated['customer_email'] ?? $customers->customer_email,
			'customer_tel' => $validated['customer_tel'] ?? $customers->customer_tel,
			'customer_address' => $validated['customer_address'] ?? $customers->customer_address,
            'communes' => $validated['communes']?? $customers->communes,
            'districts' => $validated['districts']?? $customers->districts,
            'provinces' => $validated['provinces']?? $customers->provinces,
            'villages' => $validated['villages']?? $customers->villages,
            'commune_id' => $validated['commune_id']?? $customers->commune_id,
            'district_id' => $validated['district_id']?? $customers->district_id,
            'province_id' => $validated['province_id']?? $customers->province_id,
            'village_id' => $validated['village_id']?? $customers->village_id,
            'image'        => $imageName,
		]);
	}else{
		$customers->update([
			'customer_name' => $validated['customer_name'],
			'customer_email' => $validated['customer_email'] ?? $customers->customer_email,
			'customer_tel' => $validated['customer_tel'] ?? $customers->customer_tel,
			'customer_address' => $validated['customer_address'] ?? $customers->customer_address,
            'communes' => $validated['communes']?? $customers->communes,
            'districts' => $validated['districts']?? $customers->districts,
            'provinces' => $validated['provinces']?? $customers->provinces,
            'villages' => $validated['villages']?? $customers->villages,
            'commune_id' => $validated['commune_id']?? $customers->commune_id,
            'district_id' => $validated['district_id']?? $customers->district_id,
            'province_id' => $validated['province_id']?? $customers->province_id,
            'village_id' => $validated['village_id']?? $customers->village_id,
		]);
	}
		return response()->json([
			"message" => "Customer updated successfully",
			"status" => 200,
			"data" => $customers,
		], 200);
	}

	public function destroy(string $id)
	{
		$customers = Customers::find($id);
		if($id == 1){
			return response()->json([
				"message" => "This customer cannot be deleted!",
			], 403);
		}
		if (!$customers) {
			return response()->json([
				"message" => "This customer not found!",
			], 404);
		}
		$customers->is_deleted = 1;
		$customers->save();
		return response()->json([
			"message" => "Customer deleted successfully",
			"status" => 200,
			"data" => $customers,
		], 200);
	}
}
