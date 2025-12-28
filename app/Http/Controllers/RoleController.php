<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Roles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// Removed duplicate class declaration
class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        $pid = $user->profile_id;
        $roles = DB::table('roles as r')
        ->join('users as u', 'r.created_by', '=','u.user_id')
        ->join('profiles as p', 'p.id','=','u.profile_id')
            ->where('is_deleted', 0)
            ->where('p.id', $pid)
            ->select('roles.*')
            ->get();
        if (count($roles) == 0) {
            return response()->json([
                'message' => 'Roles not found!',
                'status' => 404,
                'data' => $roles
            ]);
        }
        return response()->json([
            'message' => 'Roles selected successfully',
            'status' => 200,
            'data' => array_reverse($roles->toArray()),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'role_name' => 'required|string|max:255',
            'role_description' => 'nullable|string',
        ]);

        $data = Roles::create([
            'role_name' => $validated['role_name'],
            'role_description' => $validated['role_description'] ?? null,
            'created_by' => $uid
        ]);

        return response()->json([
            'message' => 'Role created successfully!',
            'status' => 200,
            'data' => $data,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $role = Roles::find($id);
        if (!$role || $role->is_deleted) {
            return response()->json([
                'message' => 'Role not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Role show successfully!',
            'status' => 200,
            'data' => $role,
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $role = Roles::find($id);

        if (!$role || $role->is_deleted) {
            return response()->json([
                'message' => 'This role not found!',
            ], 404);
        }

        $validated = $request->validate([
            'role_name' => 'required|string|max:255',
            'role_description' => 'nullable|string',
        ]);

        $role->update($validated);
        $role->refresh();
        return response()->json([
            'message' => 'Role updated successfully',
            'status' => 200,
            'data' => $role,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $role = Roles::find($id);
        if($id == 1 || $id == 2 || $id == 3){
            return response()->json([
                'message' => 'Cannot delete admin role',
                'status' => 400,
                'data' => null,
            ], 400);
        }
        if (!$role || $role->is_deleted) {
            return response()->json([
                'message' => 'This role not found!',
            ], 404);
        }

        $role->is_deleted = 1;
        $role->save();
        return response()->json([
            'message' => 'Role deleted successfully',
            'status' => 200,
            'data' => $role,
        ], 200);
    }
}
