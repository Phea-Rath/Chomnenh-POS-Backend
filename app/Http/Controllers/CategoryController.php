<?php

namespace App\Http\Controllers;

use App\Models\Categories;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        // $page = 2;
        $category = DB::table('categories')
            ->join('users', "categories.created_by", '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('users.profile_id', '=', $proId)
            ->where('categories.is_deleted',0)
            ->select('categories.*')
            // ->paginate($page);
            ->get();
        if (count($category) == 0) {
            return response()->json([
                'message' => 'Categories not found!',
                'status' => 404,
                // 'data'=>$students->items(),
                'data' => $category
            ]);
        }
        return response()->json([
            'message' => 'Categories selected successfully',
            'status' => 200,
            // 'data'=>$students->items(),
            'data' => array_reverse($category->toArray()),
        ]);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $validated = $request->validate([
            'category_name' => 'required|string|max:255',
        ]);

        // Create the post
        $data = Categories::create([
            'category_name' => $validated['category_name'],
            'created_by' => $uid,
        ]);

        return response()->json([
            'message' => 'Category created successfully!',
            'status' => 200,
            'data' => $data,
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $category = Categories::where('created_by', $uid)->find($id);
        if (!$category) {
            return response()->json([
                'message' => 'Category not found!',
            ], 404);
        }
        return response()->json([
            'message' => 'Category show successfully!',
            'status' => 200,
            'data' => $category,
        ], 200);
    }


    public function update(Request $request, string $id)
    {
        $category = Categories::find($id);

        if (!$category) {
            return response()->json([
                "message" => "This scale not found!",
            ], 404);
        }

        $validated = $request->validate([
            'category_name' => 'required|string|max:255',
            // 'created_by' => 'required|integer',
        ]);

        $category->update($validated);
        $category->refresh();
        return response()->json([
            "message" => "Category updated successfully",
            "status" => 200,
            "data" => $category,
        ], 200);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Categories::find($id);
        if (!$category) {
            return response()->json([
                "message" => "This category not found!",
            ], 404);
        }

        $category->is_deleted = 1;
        $category->save();
        return response()->json([
            "message" => "Category deleted successfully",
            "status" => 200,
            "data" => $category,
        ], 200);
    }
}
