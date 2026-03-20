<?php

namespace App\Http\Controllers;

use App\Models\RawMaterial;
use App\Models\Items;
use App\Models\Categories;
use App\Models\Brands;
use App\Models\Scales;
use App\Models\Image;
use App\Models\StockMaster;
use App\Models\ItemImage;
use App\Models\AttributeDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\DetailService;
use App\Services\AttributeService;
use App\Services\ItemService;
use App\Http\Controllers\ItemController;

class RawMaterialController extends Controller
{
    protected $detailService;
    protected $attrService;
    protected $itemService;

    public function __construct(DetailService $detailService, AttributeService  $attrService, ItemService $itemService)
    {
        $this->detailService = $detailService;
        $this->attrService = $attrService;
        $this->itemService = $itemService;
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search', '');

         $query = DB::table('raw_materials')
            ->leftJoin('users', 'users.id', '=', 'raw_materials.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('raw_materials.is_deleted', 0);

        // 2. Add Search Logic (Conditional)
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('raw_materials.material_name', 'LIKE', "%{$search}%")
                ->orWhere('raw_materials.material_code', 'LIKE', "%{$search}%");
                // Add other columns here if needed, e.g., ->orWhere('items.description', 'LIKE', ...)
            });
        }

        $rawItems = $query->select(
                'users.username as create_by_name',
                'raw_materials.id',
                'raw_materials.material_name',
                'raw_materials.material_code',
                'raw_materials.material_image',
                'raw_materials.primary_unit',
                'raw_materials.secondary_unit',
                'raw_materials.conversion_value',
                'raw_materials.material_cost',
                'raw_materials.created_at',
                'raw_materials.updated_at',
                DB::raw('0 as in_stock'),
            )
            ->orderBy('raw_materials.id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        // 3. Handle Empty Results
        if ($rawItems->total() == 0) {
            return response()->json([
                'message' => 'Raw Material not found!',
                'status' => 404,
                'data' => []
            ]);
        }

        $items = [];
        foreach ($rawItems as $item) {
            if ($item->material_image) {
                $filenameOnly = basename($item->material_image);
                $item->material_image = url('storage/images/' . $filenameOnly);
            }
            $stock = $this->detailService->quanRaws($item->id);
            $item->conversion_value  = number_format($item->conversion_value,'2','.','');
            $item->in_stock = $stock[0]->in_stock ?? 0;
            $items[] = $item;
        }

        return response()->json([
            'message' => 'Raw Material selected successfully',
            'status' => 200,
            'data' =>  $items,
            'pagination' => [
            'current_page' => $rawItems->currentPage(),
            'per_page'     => $rawItems->perPage(),
            'total'        => $rawItems->total(),
            'last_page'    => $rawItems->lastPage(),
        ]

        ]);
    }

    public function store(Request $request, ItemController $itemService)
    {
        $user = Auth::user();
        $uid = $user->id;
        $itemCode = 'P-' . str_pad((RawMaterial::max('id') + 1), 5, '0', STR_PAD_LEFT);
        $stock_no = now()->format('Ymd') . '-' . str_pad((StockMaster::max('stock_id') + 1), 5, '0', STR_PAD_LEFT);
        $stock_date = now()->format('Y-m-d');
         // Generate barcode
        $currentDate = Carbon::now();
        $year = $currentDate->format('y'); // Last two digits of year (e.g., 25 for 2025)
        $month = $currentDate->format('m'); // Two-digit month (e.g., 09)
        $day = $currentDate->format('d'); // Two-digit day (e.g., 01)
        $profile_id = '01'; // Assuming a fixed profile_id for this example
        $created_by = str_pad($uid, 2, '0', STR_PAD_LEFT); // Two-digit created_by (e.g., 02)

        // Count items created in the current month for barcode
        $monthStart = $currentDate->startOfMonth()->format('Y-m-d');
        $monthEnd = $currentDate->endOfMonth()->format('Y-m-d');
        $itemCount = RawMaterial::whereBetween('created_at', [$monthStart, $monthEnd])->count() + 1;
        $itemCountPadded = str_pad($itemCount, 5, '0', STR_PAD_LEFT); // Five-digit item count (e.g., 00001)

        // Construct barcode (e.g., 010225090100001)
        $code = $profile_id . $created_by . $year . $month . $day . $itemCountPadded;

        $validated = $request->validate([
            'material_name' => 'required|string|max:255',
            'material_image' => '',
            'material_code' => 'unique:raw_materials,material_code',
            'primary_unit' => 'required|string|max:100',
            'secondary_unit' => 'nullable|string|max:100',
            'conversion_value' => 'nullable|numeric',
        ]);

        $imageName = null;
        if ($request->hasFile('material_image')) {
            $file = $request->file('material_image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }


        $data = RawMaterial::create([
            'material_code' => $validated['material_code'] ?? $code,
            'material_name' => $validated['material_name'],
            'created_by' => $uid,
            'primary_unit' => $validated['primary_unit'],
            'secondary_unit' => $validated['secondary_unit'],
            'conversion_value' => $validated['conversion_value'],
            'material_image' => $imageName,
        ]);


        return response()->json([
            'message' => 'Raw material created successfully!',
            'status' => 200,
            'data' => $data,
        ], 200);
    }

    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

         $query = DB::table('raw_materials')
            ->leftJoin('users', 'users.id', '=', 'raw_materials.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('raw_materials.id', $id)
            ->where('raw_materials.is_deleted', 0);

        // 2. Add Search Logic (Conditional)
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('raw_materials.material_name', 'LIKE', "%{$search}%")
                ->orWhere('raw_materials.material_code', 'LIKE', "%{$search}%");
                // Add other columns here if needed, e.g., ->orWhere('items.description', 'LIKE', ...)
            });
        }

        $rawItems = $query->select(
                'users.username as create_by_name',
                'raw_materials.id',
                'raw_materials.material_name',
                'raw_materials.material_code',
                'raw_materials.material_image',
                'raw_materials.primary_unit',
                'raw_materials.secondary_unit',
                'raw_materials.conversion_value',
                'raw_materials.material_cost',
                'raw_materials.created_at',
                'raw_materials.updated_at',
                DB::raw('0 as in_stock'),
            )
            ->orderBy('raw_materials.id', 'DESC')->first();

        return response()->json([
            'message' => 'Raw material retrieved successfully!',
            'status' => 200,
            'data' => $rawItems,
        ], 200);
    }

    public function update(Request $request, string $id, ItemController $itemService)
    {
        $material = RawMaterial::find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Raw material not found!',
                'status' => 404,
            ]);
        }

        $validated = $request->validate([
            'material_name' => 'required|string|max:255',
            'material_image' => '',
            'primary_unit' => 'required|string|max:100',
            'secondary_unit' => 'nullable|string|max:100',
            'conversion_value' => 'nullable|numeric',
        ]);

        $imageName = null;
        if ($request->hasFile('material_image')) {
            $file = $request->file('material_image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
        }

        if($imageName){
            if ($material->material_image) {
                $oldPath = public_path('storage/images/' . $material->material_image);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
            $material->update([
                'material_name' => $validated['material_name'],
                'primary_unit' => $validated['primary_unit'],
                'secondary_unit' => $validated['secondary_unit'],
                'conversion_value' => $validated['conversion_value'],
                'material_image' => $imageName,
            ]);
        }else{
            $material->update([
                'material_name' => $validated['material_name'],
                'primary_unit' => $validated['primary_unit'],
                'secondary_unit' => $validated['secondary_unit'],
                'conversion_value' => $validated['conversion_value'],
            ]);
        }

        $material->update([
            'item_name' => $validated['material_name'],
        ]);

        if ($request->filled('attributes')) {
            AttributeDetail::where('item_id', $id)->delete();
            $request->merge(['edit_id' => $id]);
            $itemService->storeAttr($request);
        }

        return response()->json([
            'message' => 'Raw material updated successfully',
            'status' => 200,
            'data' => $material,
        ], 200);
    }

    public function destroy(string $id)
    {
        $material = RawMaterial::find($id);

        if (!$material) {
            return response()->json([
                'message' => 'Raw material not found!',
                'status' => 404,
            ]);
        }

        $material->is_deleted = 1;
        $material->save();

        return response()->json([
            'message' => 'Raw material deleted successfully',
            'status' => 200,
            'data' => $material,
        ], 200);
    }



}
