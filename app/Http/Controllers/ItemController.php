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
use App\Services\ItemService;

class ItemController extends Controller
{

    protected $attributeService;
    protected $itemService;


    public function __construct(AttributeService $attributeService, ItemService $itemService)
    {
        $this->attributeService = $attributeService;
        $this->itemService = $itemService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

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
    $attributes = json_decode($request->input('attributes'), true);
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
                    'wholesale_price' => $validated['wholesale_price'],
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
            'data'=>$items
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
            'edit_image_id.*' => 'nullable',
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
            'data'=>$items
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
            "data" => $item,
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
                "data" => $items,
            ], 200);
}
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
}
