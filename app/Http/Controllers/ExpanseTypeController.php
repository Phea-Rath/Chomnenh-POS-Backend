<?php

namespace App\Http\Controllers;

use App\Models\ExpanseTypes;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ExpanseTypeController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        // $page = 2;
        $expense_types = DB::table('expense_types')
            ->where('is_deleted', 0)
            ->where('created_by', $uid)
            // ->paginate($page);
            ->get();
        if (count($expense_types) == 0) {
            return response()->json([
                'message' => 'ExpanseTypes not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $expense_types
            ]);
        }
        return response()->json([
            'message' => 'ExpanseTypes selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($expense_types->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'expense_type_name' => 'required|string|max:255',
            'created_by' => 'required|integer',
        ]);

        $expenseTypeName = Str::lower(trim($validated['expense_type_name']));
        $exists = ExpanseTypes::where('is_deleted', 0)
            ->whereRaw('LOWER(TRIM(expense_type_name)) = ?', [$expenseTypeName])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Expanse type name already exists!',
                'status' => 409,
            ], 409);
        }

        $data = ExpanseTypes::create([
            'expense_type_name' => $expenseTypeName,
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'ExpanseType created successfully!',
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
        $expense_types = ExpanseTypes::where('created_by', $uid)
            ->where('is_deleted', 0)
            ->find($id);
        if (!$expense_types) {
            return response()->json([
                'message' => 'ExpanseType not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'ExpanseType show successfully!',
            'status' => 200,
            'data' => $expense_types,
        ], 201);
    }
    public function update(Request $request, string $id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        $expense_types = ExpanseTypes::find($id);

        if (!$expense_types) {
            return response()->json([
                "message" => "This expense type not found!",
            ], 404);
        }

        $validated = $request->validate([
            'expense_type_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        $expense_types->update($validated);

        return response()->json([
            "message" => "ExpanseType updated successfully",
            "status" => 200,
            "data" => $expense_types,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expense_types = ExpanseTypes::find($id);
        if (!$expense_types) {
            return response()->json([
                "message" => "This expense type not found!",
            ], 404);
        }

        $expense_types->is_deleted = 1;
        $expense_types->save();
        return response()->json([
            "message" => "ExpanseType deleted successfully",
            "status" => 200,
            "data" => $expense_types,
        ], 200);
    }
}
