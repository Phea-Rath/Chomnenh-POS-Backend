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

         $query = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('items.item_type', 1)
            ->where('items.is_deleted', 0);

        // 2. Add Search Logic (Conditional)
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('items.item_name', 'LIKE', "%{$search}%")
                ->orWhere('items.item_code', 'LIKE', "%{$search}%");
                // Add other columns here if needed, e.g., ->orWhere('items.description', 'LIKE', ...)
            });
        }

        $rawItems = $query->select(
                'users.username as create_by_name',
                'items.item_id as id',
                'items.item_name as material_name',
                'items.barcode as material_code',
                'items.created_at',
                'items.updated_at',
                DB::raw('0 as material_image'),
                DB::raw('0 as primary_unit'),
                DB::raw('0 as secondary_unit'),
                DB::raw('0 as conversion_value'),
                DB::raw('0 as in_stock'),
            )
            ->orderBy('items.item_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        // 3. Handle Empty Results
        if ($rawItems->total() == 0) {
            return response()->json([
                'message' => 'Items not found!',
                'status' => 404,
                'data' => []
            ]);
        }

        $items = [];
        foreach ($rawItems as $item) {
            $image = $this->itemService->getImage($item->id);
            $attrs = $this->attrService->transformAttributes($item->id);
            $item->material_image = $image[0]['image'];
            $item->primary_unit = $attrs[0]['name'];
            $item->secondary_unit = $attrs[1]['name'];
            $item->conversion_value = $attrs[1]['value'];
            $stock = $this->detailService->quanItems($item->id);
            $item->in_stock = $stock[0]->in_stock;
            $items[] = $item;
        }

        return response()->json([
            'message' => 'Raw Material selected successfully',
            'status' => 200,
            'data' =>  $rawItems,

        ]);
    }

    public function store(Request $request, ItemController $itemService)
    {
        $user = Auth::user();
        $uid = $user->id;
        $itemCode = 'P-' . str_pad((Items::max('item_id') + 1), 5, '0', STR_PAD_LEFT);
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
        $itemCount = Items::whereBetween('created_at', [$monthStart, $monthEnd])->count() + 1;
        $itemCountPadded = str_pad($itemCount, 5, '0', STR_PAD_LEFT); // Five-digit item count (e.g., 00001)

        // Construct barcode (e.g., 010225090100001)
        $code = $profile_id . $created_by . $year . $month . $day . $itemCountPadded;

        $validated = $request->validate([
            'material_name' => 'required|string|max:255',
            'material_image' => '',
            // 'primary_unit' => 'required|string|max:100',
            // 'secondary_unit' => 'nullable|string|max:100',
            // 'conversion_value' => 'nullable|numeric',
        ]);

        $category = Categories::where('category_name','production')->first();
        $brand = Brands::where('brand_name','production')->first();
        $scale = Scales::where('scale_name','production')->first();
        if(!$category){
            $category = Categories::create([
                'category_name' => 'production',
                'created_by' => $uid,
            ]);
        }
        if(!$brand){
            $brand = Brands::create([
                'brand_name' => 'production',
                'created_by' => $uid,
            ]);
        }
        if(!$scale){
            $scale = Scales::create([
                'scale_name' => 'production',
                'created_by' => $uid,
            ]);
        }

        $data = Items::create([
            'item_name' => $validated['material_name'],
            'item_code' => $itemCode,
            'barcode' => $code,
            'item_cost' => 0,
            'item_price' => 0,
            'wholesale_price' => 0,
            'category_id' => $category->category_id,
            'brand_id' => $brand->brand_id,
            'scale_id' => $scale->scale_id,
            'item_type' => 1,
            'created_by' => $uid,
            // 'primary_unit' => $validated['primary_unit'],
            // 'secondary_unit' => $validated['secondary_unit'],
            // 'conversion_value' => $validated['conversion_value'],
            // 'material_image' => $imageName,
        ]);

        $imageName = null;
        if ($request->hasFile('material_image')) {
            $file = $request->file('material_image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
            $image = Image::create([
                'image' => $imageName,
            ]);

            $image = ItemImage::create([
                'item_id' => $data->item_id,
                'image_id' => $image->id,
            ]);
        }

        $itemService->storeAttr($request);

        return response()->json([
            'message' => 'Raw material created successfully!',
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

         $rawItems = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('items.created_by', $id)
            ->where('items.is_deleted', 0)->select(
                'users.username as create_by_name',
                'items.item_id as id',
                'items.item_name as material_name',
                'items.barcode as material_code',
                'items.created_at',
                'items.updated_at',
                DB::raw('0 as material_image'),
                DB::raw('0 as primary_unit'),
                DB::raw('0 as secondary_unit'),
                DB::raw('0 as conversion_value'),
                DB::raw('0 as in_stock'),
            )
            ->first();

        // 3. Handle Empty Results
        if (!$rawItems) {
            return response()->json([
                'message' => 'Items not found!',
                'status' => 404,
                'data' => []
            ]);
        }


        $image = $this->itemService->getImage($rawItems->id);
        $attrs = $this->attrService->transformAttributes($rawItems->id);
        $rawItems->material_image = $image[0]['image'];
        $rawItems->primary_unit = $attrs[0]['name'];
        $rawItems->secondary_unit = $attrs[1]['name'];
        $rawItems->conversion_value = $attrs[1]['value'];
        $stock = $this->detailService->quanItems($rawItems->id);
        $rawItems->in_stock = $stock[0]->in_stock;


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
        ]);

        $imageName = null;
        if ($request->hasFile('material_image') && !empty($validated['material_image'])) {
            $imageId = ItemImage::where('item_id', $id)->first()->image_id;
            $file = $request->file('material_image');
            $imageName = time() . '.' . $file->getClientOriginalExtension();
            $file->storeAs('public/images', $imageName);
            $image = Image::create([
                'image' => $imageName,
            ]);

            $image = ItemImage::create([
                'item_id' => $data->item_id,
                'image_id' => $image->id,
            ]);

            if($imageId){
            Image::find($imageId)->delete();
            Image::find($imageId)->each(function($img){
                $imgPath = public_path('storage/images/' . $img->image);
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                }
            });
        }
        }

        $material->update([
            'item_code' => $validated['material_name'],
        ]);

        $itemService->storeAttr($request);

        return response()->json([
            'message' => 'Raw material updated successfully',
            'status' => 200,
            'data' => $material,
        ], 200);
    }

    public function destroy(string $id)
    {
        $material = Items::find($id);

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
