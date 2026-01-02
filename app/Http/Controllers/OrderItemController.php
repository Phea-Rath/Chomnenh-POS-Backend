<?php

namespace App\Http\Controllers;

use App\Models\OrderItems;
use App\Services\AttributeService;
use App\Services\DetailService;
use App\Services\ItemService;
use Auth;
use DB;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
     protected $attributeService;
     protected $detailService;
     protected $itemService;

    public function __construct(AttributeService $attributeService, DetailService $detailService, ItemService $itemService)
    {
        $this->attributeService = $attributeService;
        $this->detailService = $detailService;
        $this->itemService = $itemService;
    }

    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;

        $order_item = DB::table('order_items')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->join('items', 'order_items.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'order_items.*',
                'items.item_name',
                'items.item_code',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'order_masters.created_by as created_by'
            )
            ->where('order_masters.created_by', $uid)
            ->where('order_items.is_deleted', 0)
            ->get();

        if ($order_item->isEmpty()) {
            return response()->json([
                "message" => "No order item found",
                "status" => 404,
                "data" => []
            ]);
        }

        foreach($order_item as $order){
            $attrs = DB::table('order_attributes')->where('order_item_id',$order->id)->get();

            $order_attrs = [];
            foreach($attrs as $attr){

                $data = ['item_id'=>$attr->item_id,'name_id'=>$attr->attribute_id,'value_id'=>$attr->attribute_value_id];
                $request = new Request($data);
                array_push($order_attrs, $this->attributeService
                ->attrUnit($request)[0]);

            }
            $order->attributes = $order_attrs;
        }

        // ✅ Get all item_ids from collection
        $itemIds = $order_item->pluck('item_id')->unique();

        // ✅ Get images for those items
        $images = DB::table('item_images as ii')
            ->join('images as im', 'im.id', '=', 'ii.image_id')
            ->select('ii.item_id', 'im.image')
            ->whereIn('ii.item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        // ✅ Attach images to each order item
        $order_item->transform(function ($item) use ($images) {
            $item->images = $images[$item->item_id] ?? [];
            if ($images[$item->item_id]) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });



        return response()->json([
            "message" => "Stock item get successfully",
            "status" => 200,
            "data" => $order_item,
        ]);
    }

    public function monthlyOrderPercentCompare()
    {
        $user = Auth::user();
        $uid = $user->id;

        $now = \Carbon\Carbon::now();
        $currentStart = $now->copy()->startOfMonth()->toDateString();
        $currentEnd   = $now->copy()->endOfMonth()->toDateString();
        $prevStart    = $now->copy()->subMonth()->startOfMonth()->toDateString();
        $prevEnd      = $now->copy()->subMonth()->endOfMonth()->toDateString();

        $currentTotal = (float) DB::table('order_items')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->where('order_items.is_deleted', 0)
            ->whereBetween(DB::raw('DATE(order_masters.created_at)'), [$currentStart, $currentEnd])
            ->selectRaw('SUM(CASE WHEN order_masters.sale_type = "sale" THEN order_items.item_price * order_items.quantity ELSE order_items.item_wholesale_price * order_items.quantity END) as total')
            ->value('total') ?? 0.0;

        $previousTotal = (float) DB::table('order_items')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->where('order_items.is_deleted', 0)
            ->whereBetween(DB::raw('DATE(order_masters.created_at)'), [$prevStart, $prevEnd])
            ->selectRaw('SUM(CASE WHEN order_masters.sale_type = "sale" THEN order_items.item_price * order_items.quantity ELSE order_items.item_wholesale_price * order_items.quantity END) as total')
            ->value('total') ?? 0.0;

        $sum = $currentTotal + $previousTotal;

        if ($sum <= 0) {
            $currentPercent = 0.0;
            $previousPercent = 0.0;
        } else {
            $currentPercent = round(($currentTotal / $sum) * 100, 2);
            // ensure total 100% (adjust rounding drift)
            $previousPercent = round(100.0 - $currentPercent, 2);
        }

        return response()->json([
            'message' => 'Monthly order amount comparison',
            'status' => 200,
            'data' => [
                ['name'=>"thisMonth",'quantity' => $currentTotal,'persent' => $currentPercent],
                ['name'=>"lastMonth",'quantity' => $previousTotal,'persent' => $previousPercent,]
            ]
        ], 200);
    }

    public function popularSales()
    {
        $user = Auth::user();
        $uid = $user->id;

        $order_items = DB::table('order_items')
            ->join('items', 'order_items.item_id', '=', 'items.item_id')
            ->join('brands', 'items.brand_id', '=', 'brands.brand_id')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->where('order_items.is_deleted', 0)
            ->select(
                'order_items.item_id','brands.brand_name','items.item_name',
                DB::raw('
                    SUM(
                        CASE
                            WHEN order_masters.sale_type = "sale"
                                THEN order_items.item_price*order_items.quantity
                            ELSE
                                order_items.item_wholesale_price*order_items.quantity
                        END
                    ) AS total_price
                '),
                DB::raw('
                    SUM(order_items.quantity) AS total_quantity
                '),
                DB::raw('0 AS image')
            )
            ->groupBy('order_items.item_id','brands.brand_name','items.item_name')
            ->orderByDesc('total_price')
            ->limit(5)
            ->get();

        // Check if any data was found
        if ($order_items->isEmpty()) {
            return response()->json([
                'message' => 'No popular sales found!',
                'status'  => 404
            ]);
        }

        // Fix image URLs
        foreach ($order_items as $item) {
            $item->image = $this->itemService->getImage($item->item_id)[0]['image'];
        }

        return response()->json([
            'message' => 'Popular sales retrieved successfully!',
            'status'  => 200,
            'data'    => $order_items
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $order_item = $this->detailService->orderDetailById($id);

        return response()->json([
            "message" => "Stock item get successfully",
            "status" => 200,
            "data" => $order_item,
        ]);
    }

    public function quantityInOrderByItemId(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $pid = $user->profile_id;
        $item_id = $request->item_id;

        $order_item = DB::table('order_items')
           ->join('order_attributes', 'order_items.id', '=', 'order_attributes.order_item_id')
           ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
           ->join('users', 'order_masters.created_by', '=', 'users.id')
              ->where('users.profile_id', $pid)
           ->select('order_items.item_id',
           'order_attributes.order_item_id'
           , DB::raw('order_items.quantity as quantity_in_order'))
           ->groupBy('order_items.item_id','order_attributes.order_item_id')
           ->get();


        if ($order_item->isEmpty()) {
            return response()->json([
                "message" => "No order item found",
                "status" => 404,
                "data" => []
            ]);
        }
        $totalQuan = 0;
        foreach ($order_item as $item) {
            $totalQuan += $item->quantity_in_order;
            $attrs = DB::table('order_attributes')
                ->where('order_item_id', $item->order_item_id)
                ->get();

            $item_attrs = [];
            foreach ($attrs as $attr) {
                $data = [
                    'item_id' => $attr->item_id,
                    'name_id' => $attr->attribute_id,
                    'value_id' => $attr->attribute_value_id
                ];
                $req = new Request($data);
                array_push($item_attrs, $this->attributeService->attrUnit($req)[0]);
            }
            $item->attributes = $item_attrs;
        }

        $result = [];

        foreach ($order_item as $row) {

            // ALWAYS rebuild attribute key per row
            $attrKeyParts = [];

            foreach ($row->attributes as $attr) {
                // must include BOTH name_id and value_id
                $attrKeyParts[] = $attr->name_id . ':' . $attr->value_id;
            }

            // order-safe
            sort($attrKeyParts);

            // FINAL UNIQUE KEY
            $key = $row->item_id . '|' . implode('|', $attrKeyParts);

            if (!isset($result[$key])) {
                // clone row to avoid reference issues
                $result[$key] = $row;
                $result[$key]->quantity_in_order = (int)$row->quantity_in_order;
            } else {
                $result[$key]->quantity_in_order += (int)$row->quantity_in_order;
            }
        }

        $finalData = array_values($result);



        return response()->json([
            "message" => "Order item get successfully",
            "status" => 200,
            "total_quantity" => $totalQuan,
            "data" => $finalData,
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
