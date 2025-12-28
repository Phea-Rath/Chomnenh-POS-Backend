<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\AttributeService;

class orderPageController extends Controller
{

    protected $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function showInStockByItem()
    {
        $user = auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        $results = DB::table('stock_details')
            ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('warehouses', 'stock_masters.warehouse_id', '=', 'warehouses.warehouse_id')
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->join('sizes', 'items.size_id', '=', 'sizes.size_id')
            ->join('users', 'stock_masters.stock_created_by', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->select(
                'items.barcode',
                'items.item_id',
                'items.item_code',
                'items.item_name',
                'items.item_image',
                'items.item_price',
                'items.discount',
                'items.wholesale_price as item_wholesale_price',
                'categories.category_name',
                'categories.category_id',
                'items.color_id',
                'items.color_pick',
                'sizes.size_id',
                'sizes.size_name',
                DB::raw(' SUM(CASE WHEN stock_masters.stock_type_id = 1 THEN stock_details.quantity ELSE 0 END)
                - SUM(CASE WHEN stock_masters.stock_type_id = 3 THEN stock_details.quantity ELSE 0 END)
                - SUM(CASE WHEN stock_masters.stock_type_id = 5 THEN stock_details.quantity ELSE 0 END)
                + SUM(CASE WHEN stock_masters.stock_type_id = 2 THEN stock_details.quantity ELSE 0 END)
                - SUM(CASE WHEN stock_masters.stock_type_id = 4 THEN stock_details.quantity ELSE 0 END) AS in_stock
                '),
            DB::raw('SUM(CASE WHEN items.discount = 0 THEN items.item_price ELSE items.item_price - (items.item_price * (items.discount / 100)) END) as price_discount'),
            DB::raw('CASE WHEN items.discount = 0 THEN items.wholesale_price ELSE items.wholesale_price - (items.wholesale_price * (items.discount / 100)) END as wholesale_price_discount')
            )
            ->where('stock_details.is_deleted', 0)
            ->where('items.is_deleted', 0)
            ->where('warehouses.status', 'stock')
            ->where('profiles.id', $proId)
            // ->where('warehouses.warehouse_id', function ($query) {
            //     $query->selectRaw('MIN(w.warehouse_id)')->from('warehouses as w')->join('stock_masters as sm', 'sm.warehouse_id', '=', 'w.warehouse_id')->join('users as u', 'sm.stock_created_by', '=', 'u.id')->whereColumn('u.profile_id', 'profiles.id');
            // })
            ->groupBy('items.item_id', 'items.item_code',
                'items.barcode',
                'items.discount', 'items.item_name', 'items.item_image', 'items.item_price','items.wholesale_price', 'categories.category_name', 'categories.category_id', 'items.color_pick', 'items.color_id', 'sizes.size_id', 'sizes.size_name',)->orderBy('items.item_id')->get();
        foreach ($results as $item) {
            // $url = asset($item->item_image);
            if ($item->item_image) {
                $filenameOnly = basename($item->item_image);
                $imageUrl = url('storage/images/' . $filenameOnly);
                $item->item_image = $imageUrl;
            }
        }
        return response()->json(['message' => 'StockMaster show successfully!', 'status' => 200, 'data' => $results,], 200);
    }

    public function orderQuantityByItem($id):int
    {
        $user = auth()->user();
        $proId = $user->profile_id;

        $totalOrdered = DB::table('order_items as oi')
            ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->where('oi.item_id', $id)
            ->where('oi.is_deleted', 0)
            ->where('om.is_deleted', 0)
            ->sum('oi.quantity');
        if(!$totalOrdered){
            $totalOrdered = 0;
        }

        return $totalOrdered;
    }


    public function index(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;

        $itemFilter = $request->input('item_id');
        $attrKey = $request->input('attr_key');
        $attrValue = $request->input('attr_value');

        $query = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('i.is_deleted', 0)
            ->where('p.id', $proId);

        if ($itemFilter) {
            $query->where('sd.item_id', $itemFilter);
        }


        $items = $query->select(
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_cost',
                'i.item_price',
                'i.discount',
                'i.wholesale_price',
                DB::raw('i.item_price - (i.item_price * (i.discount / 100)) as price_discount'),
                DB::raw('i.wholesale_price - (i.wholesale_price * (i.discount / 100)) as wholesale_price_discount'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END)
                    + SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END)
                    AS in_stock'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS sold')
            )
            ->groupBy('i.item_id', 'i.item_code', 'i.item_name', 'i.item_cost', 'i.discount', 'i.item_price', 'i.wholesale_price')
            ->orderBy('i.item_id')
            ->get();

            foreach($items as $it){
                $orderQuan = $this->orderQuantityByItem($it->item_id);
                if($orderQuan){
                    $it->sold = $orderQuan;
                    $it->in_stock = $it->in_stock - $orderQuan;
                }
            }
        if ($items->isEmpty()) {
            return response()->json([
                'message' => 'No items found',
                'status' => 404,
                'data' => []
            ], 404);
        }

        $itemIds = $items->pluck('item_id')->toArray();

        // ----- Attributes -----
        $groupedAttrs = [];
        if (!empty($itemIds)) {
            $attrs = [];

            foreach ($itemIds as $a) {
                $attrs = $this->attributeService->transformAttributes($a);
                $groupedAttrs[$a] = ['attributes' => $attrs];
            }
        }

        // ----- Images -----
        $groupedImages = [];
        if (!empty($itemIds)) {
            $images = DB::table('item_images')
                ->join('images', 'images.id', '=', 'item_images.image_id')
                ->whereIn('item_images.item_id', $itemIds)
                ->select('item_images.item_id', 'images.id as image_id', 'images.image')
                ->orderBy('item_images.item_id')
                ->orderBy('images.id')
                ->get();

            foreach ($images as $img) {
                $url = url('storage/images/' . $img->image);
                if (!isset($groupedImages[$img->item_id])) {
                    $groupedImages[$img->item_id] = ['image' => $url, 'images' => []];
                }
                $groupedImages[$img->item_id]['images'][] = [
                    'image_id' => $img->image_id,
                    'image' => $url
                ];
            }
        }

        // ---- Build Final Data -----
        $result = [];
        foreach ($items as $it) {
            $id = $it->item_id;

            $result[] = [
                'id' => $it->item_id,
                'code' => $it->item_code,
                'barcode' => $it->barcode,
                'name' => $it->item_name,
                'cost' => (float)$it->item_cost,
                'price' => (float)$it->item_price,
                'discount' => (float)$it->discount,
                'price_discount' => (float)$it->price_discount,
                'wholesale_price_discount' => (float)$it->wholesale_price_discount,
                'wholesale_price' => (float)$it->wholesale_price,
                'in_stock' => (int)$it->in_stock,
                'stock_in' => (int)$it->stock_in,
                'stock_out' => (int)$it->stock_out,
                'sold' => (int)$it->sold,
                'image' => $groupedImages[$id]['image'] ?? null,
                'images' => $groupedImages[$id]['images'] ?? [],
                'attributes' => $groupedAttrs[$id]['attributes'] ?? [],
            ];
        }

        return response()->json([
            'message' => 'All items retrieved',
            'status' => 200,
            'data' => $result,
        ], 200);
    }


public function indexTransfer()
{
    $user = Auth::user();
    $uid = $user->id;

    $results = DB::table('stock_details')
        ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
        ->join('warehouses', 'stock_masters.warehouse_id', '=', 'warehouses.warehouse_id')
        ->join('items', 'stock_details.item_id', '=', 'items.item_id')
        ->join('categories', 'items.category_id', '=', 'categories.category_id')
        ->join('users', 'stock_masters.stock_created_by', '=', 'users.id')
        ->join('profiles', 'users.profile_id', '=', 'profiles.id')
        ->select(
            'items.item_id',
            'items.item_code',
            'items.item_name',
            'items.item_image',
            'items.item_price',
            'items.wholesale_price',
            'categories.category_name',
            DB::raw('items.item_price - (items.item_price * (items.discount / 100)) as price_discount'),
            DB::raw('items.wholesale_price - (items.wholesale_price * (items.discount / 100)) as wholesale_price_discount'),
            DB::raw('
                SUM(CASE WHEN stock_masters.stock_type_id = 1 THEN stock_details.quantity ELSE 0 END)
                + SUM(CASE WHEN stock_masters.stock_type_id = 3 THEN stock_details.quantity ELSE 0 END)
                - SUM(CASE WHEN stock_masters.stock_type_id = 5 THEN stock_details.quantity ELSE 0 END)
                + SUM(CASE WHEN stock_masters.stock_type_id = 2 THEN stock_details.quantity ELSE 0 END)
                - SUM(CASE WHEN stock_masters.stock_type_id = 4 THEN stock_details.quantity ELSE 0 END)
                AS in_stock
            ')
        )
        ->where('stock_details.is_deleted', 0)
        ->where('warehouses.status', 'stock')
        ->where('items.item_type', 0)
        ->where('profiles.id', $uid)
        ->groupBy(
            'items.item_id',
            'items.item_code',
            'items.item_name',
            'items.item_image',
            'items.item_price',
            'items.wholesale_price',
            'categories.category_name'
        )
        ->orderBy('items.item_id')
        ->get();


    // FORMAT OUTPUT
    $data = $results->map(function ($item) {

        // Build main image URL
        $mainImage = $item->item_image
            ? url('storage/images/' . basename($item->item_image))
            : null;

        return [
            "id" => $item->item_id,
            "name" => $item->item_name,
            "price" => (float)$item->item_price,
            "price_discount" => (float)$item->price_discount,
            "wholesale_price" => (float)$item->wholesale_price,
            "wholesale_price_discount" => (float)$item->wholesale_price_discount,
            "image" => $mainImage,
            "images" => [$mainImage], // YOU CAN UPDATE LATER IF YOU ADD MULTIPLE IMAGES
            "category" => $item->category_name,
            "brand" => "unknown",
            "rating" => 0,
            "reviews" => 0,
            "sold" => 0,
            "stock" => $item->in_stock,
            "discount" => 0,
            "specifications" => [
                "sizes" => []
            ],
            "in_stock" => $item->in_stock
        ];
    });

    return response()->json([
        'message' => 'Items selected successfully',
        'status' => 200,
        'data' => $data
    ], 200);
}




    public function salesPagination(Request $request)
    {
        $user = auth()->user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $itemFilter = $request->input('item_id');
        $attrKey = $request->input('attr_key');
        $attrValue = $request->input('attr_value');

        $query = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('i.is_deleted', 0)
            ->where('p.id', $proId);

        if ($itemFilter) {
            $query->where('sd.item_id', $itemFilter);
        }

        if (!empty($attrKey) && !empty($attrValue)) {
            $query->join('attribute_values as av', 'av.item_id', '=', 'sd.item_id')
                ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                ->where('a.name', $attrKey)
                ->where('av.value', $attrValue);
        }

        $paginator = $query->select(
                'i.item_id',
                'i.item_code',
                'i.item_name',
                'i.item_price',
                'i.wholesale_price',
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) + SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) - SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) - SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) - SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS in_stock'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out')
            )
            ->groupBy('i.item_id', 'i.item_code', 'i.item_name', 'i.item_price', 'i.wholesale_price')
            ->orderBy('i.item_id')
            ->paginate($limit, ['*'], 'page', $page);

        if ($paginator->isEmpty()) {
            return response()->json([
                'message' => 'No items found',
                'status' => 404,
                'data' => []
            ], 404);
        }

        // Enrich page items with attributes and images similar to stockTransfer
        $pageItems = collect($paginator->items());
        $itemIds = $pageItems->pluck('item_id')->filter()->unique()->toArray();

        // attributes
        $groupedAttrs = [];
        if (!empty($itemIds)) {
            $attrs = DB::table('attribute_values as av')
                ->join('attributes as a', 'a.id', '=', 'av.attribute_id')
                ->whereIn('av.item_id', $itemIds)
                ->select('av.item_id', 'a.name as attribute_name', 'a.type as attribute_type', 'av.value as attribute_value')
                ->get();

            foreach ($attrs as $a) {
                $key = $a->attribute_name . '-' . $a->attribute_type;
                if (!isset($groupedAttrs[$a->item_id])) {
                    $groupedAttrs[$a->item_id] = ['_keys' => [], 'attributes' => []];
                }
                if (!isset($groupedAttrs[$a->item_id]['_keys'][$key])) {
                    $value = $a->attribute_type === 'select' ? explode(',', $a->attribute_value) : $a->attribute_value;
                    $groupedAttrs[$a->item_id]['attributes'][] = ['name' => $a->attribute_name, 'type' => $a->attribute_type, 'value' => $value];
                    $groupedAttrs[$a->item_id]['_keys'][$key] = true;
                }
            }
        }

        // images
        $groupedImages = [];
        if (!empty($itemIds)) {
            $images = DB::table('item_images')
                ->join('images', 'images.id', '=', 'item_images.image_id')
                ->whereIn('item_images.item_id', $itemIds)
                ->select('item_images.item_id', 'images.id as image_id', 'images.image')
                ->orderBy('item_images.item_id')
                ->orderBy('images.id')
                ->get();

            foreach ($images as $img) {
                $url = url('storage/images/' . $img->image);
                if (!isset($groupedImages[$img->item_id])) {
                    $groupedImages[$img->item_id] = ['image' => $url, 'images' => []];
                }
                $groupedImages[$img->item_id]['images'][] = ['image_id' => $img->image_id, 'image' => $url];
                if (empty($groupedImages[$img->item_id]['image'])) {
                    $groupedImages[$img->item_id]['image'] = $url;
                }
            }
        }

        // build result items
        $modified = [];
        foreach ($pageItems as $it) {
            $id = $it->item_id ?? null;
            $entry = [
                'id' => $it->item_id,
                'code' => $it->item_code,
                'name' => $it->item_name,
                'price' => (float)$it->item_price,
                'wholesale_price' => (float)$it->wholesale_price,
                'in_stock' => (int)$it->in_stock,
                'stock_in' => (int)$it->stock_in,
                'stock_out' => (int)$it->stock_out,
                'image' => $id && isset($groupedImages[$id]) ? $groupedImages[$id]['image'] : null,
                'images' => $id && isset($groupedImages[$id]) ? $groupedImages[$id]['images'] : [],
                'attributes' => $id && isset($groupedAttrs[$id]) ? $groupedAttrs[$id]['attributes'] : [],
            ];
            $modified[] = (object)$entry;
        }

        return response()->json([
            'message' => 'Sales pagination retrieved',
            'status' => 200,
            'data' => $modified,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ]
        ], 200);
    }
}
