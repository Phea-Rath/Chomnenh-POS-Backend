<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Deliver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class DeliverController extends Controller
{
	public function index()
	{
		$user = Auth::user();
		$uid = $user->id;
		$role = $user->role_id;
		$proId = $user->profile_id;
		if ($role == 1) {
			$delivers = DB::table('delivers')
				->join('users', 'delivers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->select('delivers.*')
				->get();
		} else {
			$delivers = DB::table('delivers')
				->join('users', 'delivers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->select('delivers.*')
				->get();
		}
		if (count($delivers) == 0) {
			return response()->json([
				'message' => 'Delivers not found!',
				'status' => 404,
				'data' => $delivers
			]);
		}

		foreach ($delivers as $item) {
            if ($item->image) {
                $filenameOnly = basename($item->image);
                $item->image = url('storage/images/' . $filenameOnly);
            }
        }
		return response()->json([
			'message' => 'Delivers selected successfully',
			'status' => 200,
			'data' => array_reverse($delivers->toArray()),
		]);
	}

	public function store(Request $request)
	{
		$user = Auth::user();
		$uid = $user->id;
		$validated = $request->validate([
			'deliver_name' => 'required|string|max:255',
            'image'       => '',
		]);
		$imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
		$data = Deliver::create([
			'deliver_name' => $validated['deliver_name'],
			'created_by' => $uid,
            'image'        => $imageName,
		]);
		return response()->json([
			'message' => 'Deliver created successfully!',
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
			$delivers = DB::table('delivers')
				->join('users', 'delivers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->select('delivers.*')
				->where('delivers.deliver_id', $id)
				->get();
		} else {
			$delivers = DB::table('delivers')
				->join('users', 'delivers.created_by', '=', 'users.id')
				->where('users.profile_id', $proId)
				->where('delivers.created_by', $uid)
				->select('delivers.*')
				->where('delivers.deliver_id', $id)
				->get();
		}
		if (!$delivers || count($delivers) == 0) {
			return response()->json([
				'message' => 'Deliver not found!',
			], 404);
		}

        if ($delivers[0]->image) {
            $delivers[0]->image = url('storage/images/' . basename($delivers[0]->image));
        }
		return response()->json([
			'message' => 'Deliver show successfully!',
			'status' => 200,
			'data' => $delivers[0],
		], 201);
	}

	public function update(Request $request, string $id)
	{
        // dd($request);
		$delivers = Deliver::find($id);
		if (!$delivers) {
			return response()->json([
				"message" => "This deliver not found!",
			], 404);
		}
		$validated = $request->validate([
			'deliver_name' => 'required|string|max:255',
            'image'       => '',
		]);
		$imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }
		if($imageName){
		$delivers->update([
			'deliver_name' => $validated['deliver_name'],
            'image'        => $imageName,
		]);
	}else{
		$delivers->update([
			'deliver_name' => $validated['deliver_name'],
		]);
	}
		return response()->json([
			"message" => "Deliver updated successfully",
			"status" => 200,
			"data" => $delivers,
		], 200);
	}

	public function destroy(string $id)
	{
		$delivers = Deliver::find($id);
		if (!$delivers) {
			return response()->json([
				"message" => "This deliver not found!",
			], 404);
		}
		$delivers->delete();
		return response()->json([
			"message" => "Deliver deleted successfully",
			"status" => 200,
			"data" => $delivers,
		], 200);
	}
}
