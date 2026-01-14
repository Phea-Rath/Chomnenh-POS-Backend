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

        $rawItems = DB::table('items')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('items.is_deleted', 0)
            ->select(
                'items.*',
            )->paginate($limit, ['*'], 'page', $page);

        if ($rawItems->count() == 0) {
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
            'data' => array_reverse($items),
        ]);
    }


    public function storeAttr(Request $request)
    {

        $attributes = json_decode($request->input('attributes'), true);
        // $attributes = $request->input('attributes');
        $category_id = $request->category_id;
        $item_id = Items::max("item_id");
        // if($item_id){
        //     return response()->json([
        //         'message' => 'Attributes processed successfully!',
        //         'status' => 200,
        //         'data' => $item_id,
        //     ], 200);
        // }
        $edit_id = $request->input('edit_id');
        // dd($attributes);
        foreach ($attributes as $attr) {

            // 🔍 Check if attribute already exists
            $id = Attribute::where('name', $attr['name'])->pluck('id')->first();

            if ($id) {
                // Attribute exists → only insert value
                // AttributeValue::create([
                //     'item_id' => $item_id,
                //     'attribute_id' => $existing->id,
                //     'value' => $attr['value']
                // ]);
                $arrValue = $attr['value'];

                $values = array_map('trim', explode(',', $arrValue ));

                // dd($values);

                $payload = [
                    'item_id' => $edit_id ?? $item_id,
                    'attribute_id' => $id,
                ];

                $attr_detail = AttributeDetail::create($payload);
                foreach($values as $value){
                    $id = DB::table('attribute_values')->where('value',$value)->value('id');
                    if(!$id){
                        $attribute_value = [
                            'value' => $value,
                        ];
                    $id = AttributeValue::create($attribute_value)->id;
                    }
                    $attr_value_detail = [
                        'attribute_detail_id'=>$attr_detail->id,
                        'attribute_value_id'=>$id,
                    ];
                    AttributeValueDetail::create($attr_value_detail);
                }

                continue; // skip creating a new attribute
            }
            $user = Auth::user();
            $uid = $user->id;

            // 🆕 Create new attribute
            $attribute = Attribute::create([
                'name' => $attr['name'],
                'type' => $attr['type'] ?? null,
                'category_id' => $category_id,
                'created_by' => $uid
            ]);

            // ➕ Create attribute value
            $arrValue = config($attr['value']);

                $values = is_array($arrValue)
                    ? $arrValue
                    : (isset($arrValue) ? [$arrValue] : []);


                $payload = [
                    'item_id' => $item_id,
                    'attribute_id' => $attribute->id,
                ];

                $attr_detail = AttributeDetail::create($payload);
                foreach($values as $value){
                    $exist = DB::table('attribute_values')->where('value',$value);
                    if(!$exist){
                        $attribute_value = [
                            'value' => $value,
                        ];
                    $exist = AttributeValue::create($attribute_value);
                    }
                    $attr_value_detail = [
                        'attribute_detail_id'=>$attr_detail->id,
                        'attribute_value'=>$exist->id,
                    ];
                    AttributeValueDetail::create($attr_value_detail);
                }
        }

        return response()->json([
            'message' => 'Attributes processed successfully!',
            'status' => 200,
            'data' => $attr_detail,
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
            'discount' => 'required|integer',
            'colors' => 'nullable|array',
            'colors.*' => 'string',
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
                'colors' => $validated['colors'] ? json_encode($validated['colors']) : null,
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
            'discount' => 'required|numeric',
            'colors' => 'nullable|array',
            'colors.*' => 'string',
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

        $items = $request->input('items');
        if (!$items || !is_array($items)) {
            return response()->json([
                'message' => 'No items provided!',
                'status' => 400,
            ], 400);
        }

        $createdItems = [];
        $errors = [];
        DB::beginTransaction();
        try {
            // Get current date for barcode generation
            $currentDate = Carbon::now();
            $year = $currentDate->format('y'); // Last two digits of year (e.g., 25 for 2025)
            $month = $currentDate->format('m'); // Two-digit month (e.g., 09)
            $day = $currentDate->format('d'); // Two-digit day (e.g., 01)
            $profile_id = '01'; // Assuming a fixed profile_id for this example
            $created_by = str_pad($uid, 2, '0', STR_PAD_LEFT); // Two-digit created_by (e.g., 02)

            // Count items created in the current month for barcode
            $monthStart = $currentDate->startOfMonth()->format('Y-m-d');
            $monthEnd = $currentDate->endOfMonth()->format('Y-m-d');
            $itemCount = Items::whereBetween('created_at', [$monthStart, $monthEnd])->count();

            foreach ($items as $index => $item) {
                // Increment item count for each item
                $itemCount++;
                $itemCountPadded = str_pad($itemCount, 5, '0', STR_PAD_LEFT); // Five-digit item count (e.g., 00001)

                // Construct barcode (e.g., 010225090100001)
                $barcode = $profile_id . $created_by . $year . $month . $day . $itemCountPadded;

                // Ensure $item is an array (decode if JSON string)
                if (is_string($item)) {
                    $item = json_decode($item, true);
                }
                if (!is_array($item)) {
                    $errors[] = "Row " . ($index + 1) . " is not a valid item object.";
                    continue;
                }

                $filename = null;
                if ($request->hasFile("items.$index.item_image")) {
                    $file = $request->file("items.$index.item_image");
                    $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    $directory = 'public/images';

                    if (!Storage::exists($directory)) {
                        Storage::makeDirectory($directory);
                    }

                    $path = $file->storeAs($directory, $filename);

                        if (!$path) {
                            $errors[] = "Row " . ($index + 1) . ": Failed to upload image file.";
                            continue;
                        }
                } else {
                    $filename = null;
                }

                // Clean up and normalize input
                // $item['color_id'] = isset($item['color_id']) && is_numeric($item['color_id']) ? (int)$item['color_id'] : null;
                // $item['color_pick'] = $item['color_pick'] ?? '#000000';
                $item['item_cost'] = isset($item['item_cost']) && is_numeric($item['item_cost']) ? $item['item_cost'] : 0;
                $item['wholesale_price'] = isset($item['wholesale_price']) && is_numeric($item['wholesale_price']) ? $item['wholesale_price'] : 0;
                $item['item_price'] = isset($item['item_price']) && is_numeric($item['item_price']) ? $item['item_price'] : 0;
                // Store single import image as JSON array for consistency with multi-image support
                $item['item_image'] = $filename ? json_encode([$filename]) : null;
                $item['expire_date'] = $item['expire_date'] ?? null;
                $item['term'] = $item['term'] ?? null;
                $item['quantity'] = isset($item['quantity']) && is_numeric($item['quantity']) ? $item['quantity'] : null;

                // Validate required fields
                if (
                    empty($item['item_name']) ||
                    empty($item['category_id']) ||
                    empty($item['brand_id']) ||
                    empty($item['scale_id']) ||
                    empty($item['size_id'])
                ) {
                    $errors[] = "Row " . ($index + 1) . " missing required fields.";
                    continue;
                }

                $itemMaxId = (int) Items::max('item_id');
                $itemCode = 'PRD-' . str_pad($itemMaxId + 1, 5, '0', STR_PAD_LEFT);

                $stockMaxId = (int) StockMaster::max('stock_id');
                // $stock_no = now()->format('Ymd') . '-' . str_pad($stockMaxId + 1, 5, '0', STR_PAD_LEFT);
                // $stock_date = now()->format('Y-m-d');
                $item['item_code'] = $item['item_code'] ?? $itemCode;

                // If color_id is 0 or null, check if color_pick exists, else create new color
                if (empty($item['color_id']) || $item['color_id'] == 0) {
                    $color = Colors::where('color_pick', $item['color_pick'])->first();
                    if ($color) {
                        $item['color_id'] = $color->color_id;
                    } else {
                        $color = Colors::create([
                            'color_name' => $item['color_pick'],
                            'color_pick' => $item['color_pick'],
                            'created_by' => $uid,
                        ]);
                        $item['color_id'] = $color->color_id;
                    }
                }

                // Create item with barcode
                $createdItem = Items::create([
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'category_id' => $item['category_id'],
                    'brand_id' => $item['brand_id'],
                    'scale_id' => $item['scale_id'],
                    'discount' => 0,
                    'size_id' => $item['size_id'],
                    'color_id' => $item['color_id'],
                    'color_pick' => $item['color_pick'],
                    'item_type' => 0,
                    'item_cost' => $item['item_cost'],
                    'wholesale_price' => $item['wholesale_price'],
                    'item_price' => $item['item_price'],
                    'created_by' => $uid,
                    'item_image' => $item['item_image'],
                    'barcode' => $barcode,
                ]);

                // Only create stock if expire_date, term, and quantity are present and quantity > 0
                // if (
                //     !empty($item['expire_date']) &&
                //     !empty($item['term']) &&
                //     !empty($item['quantity']) &&
                //     is_numeric($item['quantity']) &&
                //     $item['quantity'] > 0
                // ) {
                //     $stockMaster = StockMaster::create([
                //         'stock_no' => $stock_no,
                //         'stock_type_id' => 2,
                //         'from_warehouse' => 2,
                //         'warehouse_id' => 1,
                //         'stock_date' => $stock_date,
                //         'stock_remark' => 'Imported from Excel',
                //         'stock_created_by' => $uid,
                //     ]);

                //     StockDetails::create([
                //         'stock_id' => $stockMaster->stock_id,
                //         'item_id' => $createdItem->item_id,
                //         'quantity' => $item['quantity'],
                //         'expire_date' => $item['expire_date'],
                //         'transection_date' => $stock_date,
                //     ]);
                // } else {
                //     $errors[] = "Row " . ($index + 1) . " missing expire_date, term, or quantity. Stock not created.";
                // }

                $createdItems[] = $createdItem;
            }
            DB::commit();
            return response()->json([
                'message' => 'Items imported successfully!',
                'status' => 201,
                'data' => $createdItems,
                'errors' => $errors,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Import failed: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }
}
