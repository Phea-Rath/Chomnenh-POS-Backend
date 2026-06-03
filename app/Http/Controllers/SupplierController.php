<?php

namespace App\Http\Controllers;

use App\Models\Suppliers;
use App\Services\PostImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SupplierController extends Controller
{
    protected $postImage;
	public function __construct(PostImage $postImage)
	{
        $this->postImage = $postImage;
	}

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
                ->select('users.username as created_by_name','suppliers.*')
                ->where('suppliers.is_deleted', 0)
                ->get();
        } else {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('users.username as created_by_name','suppliers.*')
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
            'description' => 'nullable|string|max:255',
            'image'       => '',
        ]);

        $imageName = null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $imageName = $this->postImage->uploadSingle($file);
            // $imageName = time() . '.' . $file->getClientOriginalExtension();
            // $file->storeAs('public/images', $imageName);
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
            'description' => $validated['description'],
            'created_by' => $uid,
            'image'        => $imageName,
        ]);

        return response()->json([
            'message' => 'Supplier created successfully!',
            'status' => 200,
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
                ->select('users.username as created_by_name','suppliers.*')
                ->where('suppliers.supplier_id', $id)
                ->where('suppliers.is_deleted', 0)
                ->first();
        } else {
            $suppliers = DB::table('suppliers')
                ->join('users', 'suppliers.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->where('suppliers.created_by', $uid)
                ->select('users.username as created_by_name','suppliers.*')
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
            'description' => 'nullable|string|max:255',
            'image'       => '',
        ]);
        $imageName = null;
        if ($request->hasFile('image')&&!empty($validated["image"])) {
            $file = $request->file('image');
            $imageName = $this->postImage->replaceSingle($supplier->image, $file);
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
            'description' => $validated['description'],
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
            'description' => $validated['description'],
        ]);
        }

        return response()->json([
            'message' => 'Supplier updated successfully',
            'status' => 200,
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

        $this->postImage->deleteSingle($supplier->image);
        $supplier->is_deleted = 1;
        $supplier->save();

        return response()->json([
            'message' => 'Supplier deleted successfully',
            'status' => 200,
        ], 200);
    }

    public function updateImage(Request $request, string $id)
    {
        $supplier = Suppliers::find($id);

        if (!$supplier) {
            return response()->json([
                "message" => "This supplier not found!",
                "status" => 404,
            ], 404);
        }

        $request->validate([
            'image' => 'required|file|image',
        ]);

        $filename = $this->postImage->replaceSingle($supplier->image, $request->file('image'));
        $supplier->image = $filename;
        $supplier->save();
        $supplier->image = url('storage/images/' . $filename);

        return response()->json([
            "message" => "Supplier image updated successfully",
            "status" => 200,
        ]);
    }

    //import suppliers from array object
	function importSuppliers(Request $request){
		$validated = $request->validate([
			'data' => 'required|array',
			'data.*.name' => 'required|string|max:255',
			'data.*.email' => 'nullable|string|max:255',
			'data.*.phone_number' => 'nullable|string|max:255',
			'data.*.address' => 'nullable|string|max:255',
		]);
		$suppliers = $request->data;

		$user = Auth::user();
		$uid = $user->id;
		$proId = $user->profile_id;
		$country_code = '+855';
		foreach ($suppliers as $supplier) {
			//remove 0 from tel if has 0 at first
			$tel = $supplier['phone_number'];
			if (strpos($tel, '0') === 0) {
				$tel = $country_code . substr($tel, 1);
			}else{
				$tel = $country_code . $tel;
			}
			//check if supplier_tel is unique
			$existsupplier = Suppliers::where('supplier_tel', $tel)->first();
			if ($existsupplier) {
				continue;
			}
			Suppliers::create([
				'supplier_name' => $supplier['name'],
				'supplier_email' => $supplier['email'] ?? null,
				'supplier_tel' => $tel,
				'supplier_address' => $supplier['address'] ?? null,
				'created_by' => $uid,
			]);
		}
		return response()->json([
			"message" => "Suppliers imported successfully",
			"status" => 200,
		], 200);
	}
}
