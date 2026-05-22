<?php

namespace App\Http\Controllers;

use App\Models\Colors;
use App\Models\Items;
use App\Models\ItemImage;
use App\Models\Image;
use App\Models\StockDetails;
use App\Models\StockMaster;
use App\Models\AttributeDetail;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Storage;
use App\Services\AttributeService;
use App\Models\AttributeValue;
use App\Models\Attribute;
use App\Models\AttributeValueDetail;
use App\Models\RawMaterial;
use App\Services\ItemService;
use App\Services\DetailService;
use App\Services\PostImage;

class ItemController extends Controller
{

    protected $attributeService;
    protected $itemService;
    protected $detailService;
    protected $postImage;


    public function __construct(AttributeService $attributeService, ItemService $itemService, DetailService $detailService, PostImage $postImage)
    {
        $this->attributeService = $attributeService;
        $this->itemService = $itemService;
        $this->detailService = $detailService;
        $this->postImage = $postImage;
    }

    public function indexMobile(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'profiles.id', '=', 'users.profile_id')
            ->where('profiles.id', $proId)
            ->where('items.item_type', 0)
            ->where('items.is_deleted', 0);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('items.item_name', 'LIKE', "%{$search}%")
                    ->orWhere('items.item_code', 'LIKE', "%{$search}%")
                    ->orWhere('items.barcode', 'LIKE', "%{$search}%");
            });
        }

        $rawItems = $query->select('items.item_id')
            ->orderBy('items.item_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawItems->total() === 0) {
            return response()->json([
                'status' => 'success',
                'message' => 'Items fetched successfully',
                'data' => [],
            ]);
        }

        $items = [];
        foreach ($rawItems as $item) {
            $mobileItem = $this->formatMobileItem((int) $item->item_id, $proId);
            if ($mobileItem) {
                $items[] = $mobileItem;
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Items fetched successfully',
            'data' => $items,
        ]);
    }
    public function showMobile($id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $data = $this->formatMobileItem((int) $id, $proId);

        if (!$data) {
            return response()->json([
                'status' => 'success',
                'message' => 'Items fetched successfully',
                'data' => [],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Items fetched successfully',
            'data' => [$data],
        ]);
    }

    private function formatMobileItem(int $itemId, int $profileId): ?array
    {
        $item = DB::table('items')
            ->leftJoin('categories', 'categories.category_id', '=', 'items.category_id')
            ->leftJoin('brands', 'brands.brand_id', '=', 'items.brand_id')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'profiles.id', '=', 'users.profile_id')
            ->where('profiles.id', $profileId)
            ->where('items.item_id', $itemId)
            ->where('items.item_type', 0)
            ->where('items.is_deleted', 0)
            ->select(
                'items.item_id',
                'items.item_code',
                'items.barcode',
                'items.item_name',
                'items.discount',
                'items.item_price',
                'items.wholesale_price',
                'items.item_cost',
                'items.category_id',
                'items.brand_id',
                'categories.category_name',
                'brands.brand_name',
                'users.username as created_by_name',
                'items.created_at',
                'items.updated_at'
            )
            ->first();

        if (!$item) {
            return null;
        }

        $imageRows = DB::table('item_images')
            ->join('images', 'images.id', '=', 'item_images.image_id')
            ->where('item_images.item_id', $itemId)
            ->select('images.id as image_id', 'images.image')
            ->orderBy('images.id', 'asc')
            ->get();

        $images = [];
        foreach ($imageRows as $row) {
            $images[] = url('storage/images/' . $row->image);
        }

        $rawAttributes = $this->attributeService->transformAttributes($itemId);
        $stock = $this->detailService->quanItems($itemId)[0];
        $attributes = [];
        foreach ($rawAttributes as $attribute) {
            $name = $attribute['name'] ?? null;
            if (!$name) {
                continue;
            }

            $value = $attribute['value'] ?? null;
            if (is_array($value)) {
                $value = implode(', ', array_map(function ($v) {
                    return $v['value'] ?? '';
                }, $value));
            }

            $attributes[$name] = $value;
        }

        $discount = (float) $item->discount;
        $retail = (float) $item->item_price;
        $wholesale = (float) $item->wholesale_price;

        return [
            'id' => (int) $item->item_id,
            'code' => $item->item_code,
            'barcode' => $item->barcode,
            'name' => $item->item_name,
            'price' => [
                'currency' => 'USD',
                'retail' => $retail,
                'retail_discount' => round($retail - ($retail * $discount / 100), 2),
                'wholesale' => $wholesale,
                'wholesale_discount' => round($wholesale - ($wholesale * $discount / 100), 2),
                'cost' => (float) $item->item_cost,
            ],
            'discount_percent' => $discount,
            'category_id' => (int) $item->category_id,
            'brand_id' => (int) $item->brand_id,
            'attributes' => $attributes,
            'images' => $images,
            'created_by' => $item->created_by_name,
            'created_at' => Carbon::parse($item->created_at)->format('Y-m-d H:i:s'),
            'stock'=>$stock
        ];
    }

    public function getAllItems(Request $request)
    {
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $categoryId = $request->input('category_id', 0);
        $brandId = $request->input('brand_id', 0);
        $profileId = $request->input('profile_id', 0);
        $priceRange = $request->input('price_range', 0);
        $search = $request->input('search');

        $query = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('items.item_type', 0)
            ->where('items.is_deleted', 0);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('items.item_name', 'LIKE', "%{$search}%")
                ->orWhere('items.item_code', 'LIKE', "%{$search}%");
            });
        }

        if($priceRange != null && $priceRange != 0)
        {
            $query->where('items.item_price', '<=', $priceRange);
        }

        if($profileId != 0)
        {
            $query->where('profiles.id', $profileId);
        }
        if($categoryId != 0)
        {
            $query->where('items.category_id', $categoryId);
        }
        if($brandId != 0)
        {
            $query->where('items.brand_id', $brandId);
        }

        $rawItems = $query->select('items.*')
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
            $items[] = $this->itemService->getItem($item->item_id);
        }

        return response()->json([
            'message' => 'Items selected successfully',
            'status' => 200,
            'data' => $items,
            'pagination' => [
                'current_page' => $rawItems->currentPage(),
                'per_page' => $rawItems->perPage(),
                'total' => $rawItems->total(),
                'last_page' => $rawItems->lastPage(),
            ]
        ]);
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $categoryId = $request->input('category_id', 0);
        $brandId = $request->input('brand_id', 0);


        // 1. Capture the search term
        $search = $request->input('search');


        $query = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('items.item_type', 0)
            ->where('items.is_deleted', 0);

        // 2. Add Search Logic (Conditional)
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('items.item_name', 'LIKE', "%{$search}%")
                ->orWhere('items.item_code', 'LIKE', "%{$search}%");
                // Add other columns here if needed, e.g., ->orWhere('items.description', 'LIKE', ...)
            });
        }
        if($categoryId != 0 && $categoryId != "" && $categoryId != null) {
            $query->where('items.category_id', $categoryId);
        }
        if($brandId != 0 && $brandId != "" && $brandId != null) {
            $query->where('items.brand_id', $brandId);
        }

        $rawItems = $query->select('items.*')
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
            $items[] = $this->itemService->getItem($item->item_id);
        }

        return response()->json([
            'message' => 'Items selected successfully',
            'status' => 200,
            'data' => $items,
            'pagination' => [
                'current_page' => $rawItems->currentPage(),
                'per_page' => $rawItems->perPage(),
                'total' => $rawItems->total(),
                'last_page' => $rawItems->lastPage(),
            ]
        ]);
    }


    public function storeAttr(Request $request)
{
    $attributes = $request->input('attributes');

    if (is_string($attributes)) {
        $attributes = json_decode($attributes, true);
    }
    $category_id = $request->category_id;
    $item_id = Items::max('item_id');
    $edit_id = $request->input('edit_id');
    $user = Auth::user();

    foreach ($attributes as $attr) {

        // Check if attribute exists
        $attributeId = Attribute::where('name', $attr['name'])->value('id');

        // Create attribute if not exists
        if (!$attributeId) {
            $attribute = Attribute::create([
                'name' => $attr['name'],
                'type' => $attr['type'] ?? null,
                'category_id' => $category_id,
                'created_by' => $user->id,
            ]);
            $attributeId = $attribute->id;
        }

        // Create attribute detail
        $attr_detail = AttributeDetail::create([
            'item_id' => $edit_id ?? $item_id,
            'attribute_id' => $attributeId,
        ]);

        // Handle values
        $values = array_map('trim', explode(',', $attr['value']));

        foreach ($values as $value) {

            // Find or create attribute value
            $valueId = DB::table('attribute_values')
                ->where('value', $value)
                ->value('id');

            if (!$valueId) {
                $valueId = AttributeValue::create([
                    'value' => $value,
                ])->id;
            }

            // Attach value to attribute detail
            AttributeValueDetail::create([
                'attribute_detail_id' => $attr_detail->id,
                'attribute_value_id' => $valueId,
            ]);
        }
    }

    return response()->json([
        'message' => 'Attributes processed successfully!',
        'status' => 200,
        'data' => true,
    ], 200);
}






    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $now = now();
        $count = Items::join('users as u', 'items.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->where('pr.id', $proId)
            ->whereYear('items.created_at', $now->year)
            ->whereMonth('items.created_at', $now->month)
            ->count();
        $itemCode = 'PRO-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
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
        $barcode = $profile_id . $created_by . $year . $month . $day . $itemCountPadded;

        $validated = $request->validate([
            'item_code' => 'string|max:255|nullable',
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'scale_id' => 'required|integer',
            'discount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'wholesale_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_images' => 'nullable|array',
            'item_images.*' => '',
        ]);

        $itemCodeValue = $validated['item_code'] ?? $itemCode;

        $duplicateQuery = DB::table('items')
            ->join('users', 'users.id', '=', 'items.created_by')
            ->join('profiles', 'profiles.id', '=', 'users.profile_id')
            ->where([
                ['profiles.id', '=', $proId],
                ['items.item_type', '=', 0],
                ['items.is_deleted', '=', 0],
                ['items.item_name', '=', $validated['item_name']],
                ['items.item_code', '=', $itemCodeValue],
                ['items.item_price', '=', $validated['item_price']],
            ])
            ->exists();

        if ($duplicateQuery) {
            return response()->json([
                'message' => 'Duplicate item detected for this profile.',
                'status' => 409,
                'data' => null,
            ], 409);
        }

        if (is_array($request->item_images) && count($request->item_images) > 0) {
                $storedImageValue = null;
                $files = $request->item_images;

                // Ensure it's always an array
                if (!is_array($files)) {
                    $files = [$files];
                }
                $storedImages = [];

                // Create item with barcode
                $items = Items::create([
                    'item_code' => empty($validated['item_code']) ? $itemCode : $validated['item_code'],
                    'item_name' => $validated['item_name'],
                    'category_id' => $validated['category_id'],
                    'brand_id' => $validated['brand_id'],
                    'scale_id' => $validated['scale_id'],
                    'discount' => $validated['discount']??0,
                    'item_type' => 0,
                    'item_cost' => $validated['item_cost'],
                    'item_price' => $validated['item_price'],
                    'wholesale_price' => $validated['wholesale_price'] ?? $validated['item_price'],
                    'created_by' => $uid,
                    // 'item_image' => $storedImageValue,
                    'barcode' => $barcode,
                ]);

                foreach ($files as $file) {
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs('public/images', $filename);
                    $storedImages[] = $filename;
                    $image = Image::create([
                        'image' => $filename,
                    ]);

                    $image = ItemImage::create([
                        'item_id' => $items->item_id,
                        'image_id' => $image->id,
                    ]);
                }
                $storedImageValue = json_encode($storedImages);


        } else {

            // Create item with barcode
            $items = Items::create([
                'item_code' => empty($validated['item_code']) ? $itemCode : $validated['item_code'],
                'item_name' => $validated['item_name'],
                'category_id' => $validated['category_id'],
                'brand_id' => $validated['brand_id'],
                'scale_id' => $validated['scale_id'],
                'discount' => $validated['discount']??0,
                'item_type' => 0,
                'item_cost' => $validated['item_cost'],
                'item_price' => $validated['item_price'],
                'wholesale_price' => $validated['wholesale_price'],
                'created_by' => $uid,
                // 'item_image' => null,
                'barcode' => $barcode,
            ]);
        }

        $this->storeAttr($request);

        return response()->json([
            'message'=>'item created successfully',
            'status'=>200,
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $data = $this->itemService->getItem($id);

        return response()->json([
            "message" => "Item retrieved successfully",
            "status" => 200,
            "data" => $data
        ]);
    }






    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $items = Items::find($id);
        if (!$items) {
            return response()->json([
                "message" => "This item not found!",
            ], 404);
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'category_id' => 'required|integer',
            'brand_id' => 'required|integer',
            'scale_id' => 'required|integer',
            'discount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'wholesale_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'item_images' => 'nullable|array',
            'item_images.*' => '',
            'edit_image_id' => 'nullable',
        ]);

        // Handle uploaded images (replace existing if new ones provided)
        $storedImageValue = $items->item_image; // default: keep existing
        if ($request->hasFile('item_images')) {
            $files = $request->file('item_images');
            $filenames = [];
            $directory = 'public/images';

            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            foreach ($files as $file) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $path = $file->storeAs($directory, $filename);
                if (!$path) {
                    return response()->json([
                        'message' => 'Failed to upload one of the images',
                    ], 500);
                }
                $filenames[] = $filename;
                $image = Image::create([
                    'image' => $filename,
                ]);

                $image = ItemImage::create([
                    'item_id' => $items->item_id,
                    'image_id' => $image->id,
                ]);
            }

            // delete previous images if present
            if ($items->item_image) {
                $existing = json_decode($items->item_image, true);
                if (is_array($existing)) {
                    foreach ($existing as $old) {
                        $oldPath = public_path('storage/images/' . $old);
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                } else {
                    $oldPath = public_path('storage/images/' . $items->item_image);
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
            }

            $storedImageValue = json_encode($filenames);
        }

        // Update item fields
        $items->update([
            'item_name' => $validated['item_name'],
            'category_id' => $validated['category_id'],
            'brand_id' => $validated['brand_id'],
            'scale_id' => $validated['scale_id'],
            'discount' => $validated['discount'] ?? 0,
            'item_type' => 0,
            'item_cost' => $validated['item_cost'],
            'item_price' => $validated['item_price'],
            'wholesale_price' => $validated['wholesale_price'],
        ]);

        if(is_array($validated["edit_image_id"])){
            Image::whereIn('id',$validated["edit_image_id"])->delete();
            Image::find($validated["edit_image_id"])->each(function($img){
                $imgPath = public_path('storage/images/' . $img->image);
                if (file_exists($imgPath)) {
                    @unlink($imgPath);
                }
            });
        }

        AttributeDetail::where('item_id', $id)->delete();

        $this->storeAttr($request);
        return response()->json([
            'message'=>'item update successfully',
            'status'=>200,
        ]);
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $item = Items::find($id);
        if (!$item) {
            return response()->json([
                "message" => "This item not found!",
            ], 404);
        }

        $item->is_deleted = 1;
        $item->save();
        return response()->json([
            "message" => "Item deleted successfully",
            "status" => 200,
            "data" => $item,
        ], 200);
    }

    public function cancelDel(string $id)
    {
        $item = Items::find($id);
        if (!$item) {
            return response()->json([
                "message" => "This item not found!",
            ], 404);
        }

        $item->is_deleted = 0;

        $item->save();
        return response()->json([
            "message" => "Item deleted successfully",
            "status" => 200,
        ], 200);
    }


    public function deleted(string $id)
    {
        $item = Items::find($id);
        if (!$item) {
            return response()->json([
                "message" => "This item not found!",
            ], 404);
        }

        $imageIds = DB::table('item_images')->where('item_id', $id)->pluck('image_id');
        $images = DB::table('images')->whereIn('id', $imageIds)->select('image')->get();




        if (count($images) > 0) {
            $item->delete();
            foreach($images as $i){
                $imagePath = public_path('storage/images/' . $i->image);
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                    // dd($imagePath);
                }
            }
            return response()->json([
                "message" => "Item deleted successfully",
                "status" => 200,
            ], 200);
}
    }

    public function updateImage(Request $request, string $id)
    {
        $item = Items::find($id);

        if (!$item) {
            return response()->json([
                "message" => "This item not found!",
                "status" => 404,
            ], 404);
        }

        $validated = $request->validate([
            'images' => 'required|array|min:1',
            'images.*' => 'required|file|image',
            'delete_image_ids' => 'nullable|array',
            'delete_image_ids.*' => 'integer|exists:images,id',
        ]);

        if (!empty($validated['delete_image_ids'])) {
            $existingIds = ItemImage::where('item_id', $item->item_id)
                ->whereIn('image_id', $validated['delete_image_ids'])
                ->pluck('image_id')
                ->toArray();

            if (!empty($existingIds)) {
                $this->postImage->deleteItemImages($existingIds);
            }
        }

        $files = $request->file('images', []);
        $uploadedImages = $this->postImage->attachItemImages($item->item_id, $files);

        return response()->json([
            "message" => "Item images updated successfully",
            "status" => 200,
            "data" => [
                'item_id' => $item->item_id,
                'images' => $uploadedImages,
            ],
        ]);
    }

    public function importItem(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $itemsData = $request->input('items'); // This retrieves the JSON strings
        $itemsFiles = $request->file('items'); // This retrieves the uploaded files

        if (empty($itemsData)) {
            return response()->json(['message' => 'No data provided', 'status' => 400], 400);
        }
        $itemCode = 'PRD-' . str_pad((Items::max('item_id') + 1), 5, '0', STR_PAD_LEFT);
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
        $barcode = $profile_id . $created_by . $year . $month . $day . $itemCountPadded;

        try {
            DB::beginTransaction();

            foreach ($itemsData as $index => $jsonString) {
                // 1. Decode the JSON string sent from React
                $data = json_decode($jsonString, true);

                // 2. Insert the Item into the database
                $item = Items::create([
                    'item_code' => $itemCode,
                    'item_name'         => $data['item_name'],
                    'item_price'        => $data['item_price'],
                    'item_cost'         => 0,
                    'wholesale_price' => $data['wholesale_price'],
                    'category_id'  => $data['category_id'],
                    'brand_id'     => $data['brand_id'],
                    'scale_id'     => $data['scale_id'],
                    'created_by'   => $uid,
                    'barcode' => $barcode,
                ]);
                $request = new Request($data);
                $this->storeAttr($request);

                // 3. Handle Images for this specific item index
                if (isset($itemsFiles[$index]['images'])) {
                    foreach ($itemsFiles[$index]['images'] as $file) {
                        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                        $file->storeAs('public/images', $filename);
                        $image = Image::create([
                            'image' => $filename,
                        ]);

                        $image = ItemImage::create([
                            'item_id' => $item->item_id,
                            'image_id' => $image->id,
                        ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Items imported successfully!',
                'status'  => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
                'status'  => 500
            ], 500);
        }
    }


    function filterItemsByCode(Request $request, $type)
    {
        $validate = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.code' => 'required|string',
            'data.*.quantity' => 'required|integer',
            'data.*.cost' => 'required|numeric',
        ]);
        $messing_code = '';
        $result = [];
        try {
            $data = $request->input('data');
            $codes = array_column($data, 'code');
            $dataByCode = array_column($data, null, 'code');
            DB::beginTransaction();
            if($type == 'material'){
                $items = RawMaterial::whereIn('material_code', $codes)->get();
                $foundCodes = $items->pluck('item_code')->toArray();

                $missingCodes = array_diff($codes, $foundCodes);

                if (!empty($missingCodes)) {
                    $messing_code .=implode(', ',$missingCodes);
                }
                foreach($items as $index => $item){
                    $matchedData = $dataByCode[$item->material_code] ?? null;
                    $quantity = $matchedData['quantity'] ?? 0;
                    $cost = $matchedData['cost'] ?? null;
                    $result[] = [
                        'id' => $item->material_id,
                        'code' => $item->material_code,
                        'name' => $item->material_name,
                        'price' => $item->material_price,
                        'quantity' => $quantity,
                        'cost' => $cost ?? $item->material_cost,
                    ];
                }
            }else{
                $items = Items::whereIn('item_code', $codes)->get();

                $foundCodes = $items->pluck('item_code')->toArray();

                $missingCodes = array_diff($codes, $foundCodes);

                if (!empty($missingCodes)) {
                    $messing_code .=implode(', ',$missingCodes);
                }
                foreach($items as $index => $item){
                    $matchedData = $dataByCode[$item->item_code] ?? null;
                    $quantity = $matchedData['quantity'] ?? 0;
                    $cost = $matchedData['cost'] ?? null;
                    $result[] = [
                        'id' => $item->item_id,
                        'code' => $item->item_code,
                        'name' => $item->item_name,
                        'price' => $item->item_price,
                        'quantity' => $quantity,
                        'cost' => $cost ?? $item->item_cost,
                    ];
                }
            }

            if ($items) {
                return response()->json([
                    'message' => 'Item found successfully',
                    'missing_codes' => $messing_code,
                    'status'  => 200,
                    'data'    => $result
                ], 200);
            }
            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
                'status'  => 500
            ], 500);
        }
    }

    function filterItemsByCodeNotType(Request $request)
    {
        $validate = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.code' => 'required|string',
            'data.*.quantity' => 'required|integer',
            'data.*.price' => 'required|numeric',
        ]);
        $messing_code = '';
        $result = [];
        try {
            $data = $request->input('data');
            $codes = array_column($data, 'code');
            $dataByCode = array_column($data, null, 'code');
            DB::beginTransaction();

            $items = Items::whereIn('item_code', $codes)->get();

            $foundCodes = $items->pluck('item_code')->toArray();

            $missingCodes = array_diff($codes, $foundCodes);

            if (!empty($missingCodes)) {
                $messing_code .=implode(', ',$missingCodes);
            }
            foreach($items as $index => $item){
                $matchedData = $dataByCode[$item->item_code] ?? null;
                $quantity = $matchedData['quantity'] ?? 0;
                $price = $matchedData['price'] ?? 0;
                $result[] = [
                    'id' => $item->item_id,
                    'code' => $item->item_code,
                    'name' => $item->item_name,
                    'price' => $price ?? $item->wholesale_price,
                    'quantity' => $quantity,
                ];
            }


            if ($items) {
                return response()->json([
                    'message' => 'Item found successfully',
                    'missing_codes' => $messing_code,
                    'status'  => 200,
                    'data'    => $result
                ], 200);
            }
            DB::commit();
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
                'status'  => 500
            ], 500);
        }
    }
}
