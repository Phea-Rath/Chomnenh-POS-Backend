<?php

namespace App\Http\Controllers;

use App\Events\OnlineEvent;
use App\Models\StockDetails;
use App\Models\StockMaster;
use App\Models\ExchangeRate;
use App\Models\StockAttribute;
use App\Services\DetailService;
use App\Services\ItemService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockMasterController extends Controller
{

    protected $detailService;
    protected $itemService;

    public function __construct(DetailService $detailService, ItemService $itemService)
    {
        $this->detailService = $detailService;
        $this->itemService = $itemService;
    }

    public function index()
{
    $user = Auth::user();
    $uid = $user->id;
    $proId = $user->profile_id;

    // MAIN STOCK MASTER QUERY
    $stock_masters = DB::table('stock_masters as sm')
        ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
        ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
        ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
        ->join('users as s', 'sm.stock_created_by', '=', 's.id')
        ->join('profiles as p', 's.profile_id', '=', 'p.id')
        ->select(
            'from_w.warehouse_name as from_warehouse_name',
            'to_w.warehouse_name as to_warehouse_name',
            's.username as created_by_name',
            'st.stock_type_name',
            'sm.*'
        )
        ->where('p.id', $proId)
        ->where('sm.is_deleted', 0)
        ->where('to_w.warehouse_id', 1)
        ->where('sm.stock_created_by', $uid)
        ->get();

    if ($stock_masters->count() == 0) {
        return response()->json([
            'message' => 'StockMaster not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    // BUILD DATA RESULT
    $data = $stock_masters->map(function ($master) {

        return [
            ...((array)$master),
            'items' => $this->detailService->stockDetail($master->stock_id)
        ];
    });

    return response()->json([
        'message' => 'StockMaster selected successfully',
        'status'  => 200,
        'data'    => array_reverse($data->toArray()),
    ]);
}


    public function popularStockIn(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);

        $popular = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sm.stock_type_id', 2) // stock_in
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('p.id', $proId)
            ->select(
                'i.item_id',
                'i.item_name',
                'b.brand_name',
                DB::raw('0 as image'),
                DB::raw('0 as images'),
                DB::raw('SUM(sd.quantity) as total_quantity')
            )
            ->groupBy('i.item_id', 'i.item_name', 'b.brand_name')
            ->orderByDesc('total_quantity')
            ->limit($limit)
            ->get();

        // normalize image url
        foreach ($popular as $item) {
            $imagelist = $this->itemService->getImage($item->item_id);
            $item->images = !empty($imagelist) ? $imagelist : null;
            $item->image = !empty($imagelist) ? $imagelist[0]['image'] : null;
        }

        if ($popular->isEmpty()) {
            return response()->json([
                'message' => 'No popular stock-in items found!',
                'status' => 404,
                'data' => []
            ], 404);
        }

        return response()->json([
            'message' => 'Popular stock-in items retrieved',
            'status' => 200,
            'data' => $popular->toArray()
        ], 200);
    }


    public function indexPagination(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        $stock_masters = DB::table('stock_masters as sm')
        ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
        ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
        ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
        ->join('users as s', 'sm.stock_created_by', '=', 's.id')
        ->join('profiles as p', 's.profile_id', '=', 'p.id')
        ->select(
            'from_w.warehouse_name as from_warehouse_name',
            'to_w.warehouse_name as to_warehouse_name',
            's.username as created_by_name',
            'st.stock_type_name',
            'sm.*'
        )
        ->where('p.id', $proId)
        ->where('sm.is_deleted', 0)
        ->where('sm.stock_created_by', $uid)
        ->paginate($limit, ['*'], 'page', $page);

    if ($stock_masters->count() == 0) {
        return response()->json([
            'message' => 'StockMaster not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    // BUILD DATA RESULT
    $data = $stock_masters->map(function ($master) {

        return [
            ...((array)$master),
            'items' => $this->detailService->stockDetail($master->stock_id)
        ];
    });

        return response()->json([
            'message' => 'StockMaster pagination selected successfully',
            'status' => 200,
            'data' => $data->toArray(), // Only current page data
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page' => $stock_masters->perPage(),
                'total' => $stock_masters->total(),
                'last_page' => $stock_masters->lastPage(),
            ]
        ]);
    }


    public function stockTransection(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        // Paginated stock details summary
        $stock_masters = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('warehouses as wh_from', 'sm.from_warehouse', '=', 'wh_from.warehouse_id')
            ->join('warehouses as wh_to', 'sm.warehouse_id', '=', 'wh_to.warehouse_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('categories as c', 'i.category_id', '=', 'c.category_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->select(
                'sm.stock_id',
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'sd.expire_date',
                'i.created_by',
                'i.is_deleted',
                'sm.from_warehouse',
                'wh_from.warehouse_name as from_warehouse_name',
                'sm.warehouse_id',
                'wh_to.warehouse_name as to_warehouse_name',
                DB::raw('0 as images'),
                DB::raw('0 as image'),
                DB::raw('SUM(sd.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_sale'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste'),
                'sm.stock_date as created_at'
            )
            ->where('p.id', $proId)
            ->where('sd.is_deleted', 0)
            ->whereNotIn('sm.from_warehouse', [2,3,4])
            ->whereNotIn('sm.warehouse_id', [2,3,4])
            ->groupBy(
                'sm.stock_id',
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'i.created_by',
                'sd.expire_date',
                'i.is_deleted',
                'sm.from_warehouse',
                'wh_from.warehouse_name',
                'sm.warehouse_id',
                'wh_to.warehouse_name',
                'sm.stock_date'
            )
            ->orderBy('i.item_id')
            ->paginate($limit, ['*'], 'page', $page);
            foreach ($stock_masters as $stock_master) {
                $imagelist = $this->itemService->getImage($stock_master->item_id);
                $stock_master->images = !empty($imagelist) ? $imagelist : null;
                $stock_master->image = !empty($imagelist) ? $imagelist[0]['image'] : null;
            }




        if ($stock_masters->isEmpty()) {
            return response()->json([
                'message' => 'No item stock summary found!',
                'status' => 200,
                'data' => []
            ]);
        }

        // Enrich current page items using ItemController-like grouping
        $pageItems = collect($stock_masters->items());

            return response()->json([
                'message' => 'StockMaster summary selected successfully',
                'status' => 200,
                'data' => $pageItems->toArray(),
                'pagination' => [
                    'current_page' => $stock_masters->currentPage(),
                    'per_page' => $stock_masters->perPage(),
                    'total' => $stock_masters->total(),
                    'last_page' => $stock_masters->lastPage(),
                ]
            ]);

    }
    public function stockTracking(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);

        // Paginated stock details summary
        $stock_masters = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('warehouses as wh_from', 'sm.from_warehouse', '=', 'wh_from.warehouse_id')
            ->join('warehouses as wh_to', 'sm.warehouse_id', '=', 'wh_to.warehouse_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('categories as c', 'i.category_id', '=', 'c.category_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->select(
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'i.created_by',
                'i.is_deleted',
                DB::raw('0 as images'),
                DB::raw('0 as image'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_sale'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste'),
            )
            ->where('p.id', $proId)
            ->where('sd.is_deleted', 0)
            ->whereNotIn('sm.from_warehouse', [3,4])
            ->whereNotIn('sm.warehouse_id', [2,3,4])
            ->groupBy(
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'i.created_by',
                'i.is_deleted',
            )
            ->orderBy('i.item_id')
            ->paginate($limit, ['*'], 'page', $page);
            foreach ($stock_masters as $stock_master) {
                $imagelist = $this->itemService->getImage($stock_master->item_id);
                $stock_master->images = !empty($imagelist) ? $imagelist : null;
                $stock_master->image = !empty($imagelist) ? $imagelist[0]['image'] : null;
            }




        if ($stock_masters->isEmpty()) {
            return response()->json([
                'message' => 'No item stock summary found!',
                'status' => 404,
                'data' => []
            ]);
        }

        // Enrich current page items using ItemController-like grouping
        $pageItems = collect($stock_masters->items());

            return response()->json([
                'message' => 'StockMaster summary selected successfully',
                'status' => 200,
                'data' => $pageItems->toArray(),
                'pagination' => [
                    'current_page' => $stock_masters->currentPage(),
                    'per_page' => $stock_masters->perPage(),
                    'total' => $stock_masters->total(),
                    'last_page' => $stock_masters->lastPage(),
                ]
            ]);

    }



    public function stockByWarehouse(Request $request, $warehouseId)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        // $limit = $request->input('limit', 10);
        // $page = $request->input('page', 1);

        // Paginated stock details summary
        $stock_masters = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('warehouses as wh_from', 'sm.from_warehouse', '=', 'wh_from.warehouse_id')
            ->join('warehouses as wh_to', 'sm.warehouse_id', '=', 'wh_to.warehouse_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('categories as c', 'i.category_id', '=', 'c.category_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->select(
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'i.is_deleted',
                DB::raw('0 as images'),
                DB::raw('0 as image'),
                DB::raw('0 as stock'),
                // 'sm.created_at'
            )
            ->where('p.id', $proId)
            ->where('sd.is_deleted', 0)
            ->whereIn('sm.stock_type_id', [1,2]) // stock in
            ->where('sm.warehouse_id', $warehouseId)
            ->groupBy(
                'i.item_id',
                'i.item_code',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'i.item_cost',
                'i.wholesale_price',
                'i.category_id',
                'c.category_name',
                'i.brand_id',
                'b.brand_name',
                'i.is_deleted',
            )
            ->orderBy('i.item_id')->get();
            // ->paginate($limit, ['*'], 'page', $page);
            foreach ($stock_masters as $stock_master) {
                $imagelist = $this->itemService->getImage($stock_master->item_id);
                $stock_master->stock = $this->detailService->quanItems($stock_master->item_id)[0];
                $stock_master->images = !empty($imagelist) ? $imagelist : null;
                $stock_master->image = !empty($imagelist) ? $imagelist[0]['image'] : null;
            }




        if ($stock_masters->isEmpty()) {
            return response()->json([
                'message' => 'No item stock found!',
                'status' => 404,
                'data' => []
            ]);
        }

        // Enrich current page items using ItemController-like grouping
        // $pageItems = collect($stock_masters->items());

            return response()->json([
                'message' => 'StockMaster selected successfully',
                'status' => 200,
                'data' => $stock_masters->toArray(),
                // 'pagination' => [
                //     'current_page' => $stock_masters->currentPage(),
                //     'per_page' => $stock_masters->perPage(),
                //     'total' => $stock_masters->total(),
                //     'last_page' => $stock_masters->lastPage(),
                // ]
            ]);

    }


    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $stock_no = now()->format('Ymd') . '-' . str_pad((StockMaster::max('stock_id') + 1), 5, '0', STR_PAD_LEFT);
        $stock_date = now()->format('Y-m-d');
        $validated = $request->validate([
            'stock_type_id' => 'required|integer',
            'from_warehouse' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'stock_remark' => 'required|string|max:255',
            'items' => 'array||min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer',
            'items.*.item_cost' => 'required|numeric',
            'items.*.expire_date' => 'required|date',
        ]);



        $exchange_rate = ExchangeRate::find($proId);
        // Create the post
        $data = StockMaster::create([
            'stock_no' => $stock_no,
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => $validated['from_warehouse'],
            'warehouse_id' => $validated['warehouse_id'],
            // 'order_id' => $validated['order_id'] || null,
            'stock_date' => $stock_date,
            'stock_remark' => $validated['stock_remark'],
            'stock_created_by' => $uid,
        ]);
        $items = [];
        foreach ($validated['items'] as $item) {
            // $attr = json_encode($item['attributes']);


            $items[] = StockDetails::create([
                'stock_id' => StockMaster::max('stock_id'),
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'],
                // 'attributes' => json_encode($attr ?? []),
                'transection_date' => $stock_date,
                'expire_date' => $item['expire_date'],
            ]);
        }

        broadcast(new OnlineEvent('stock', $proId))->toOthers();
        return response()->json([
            'message' => 'StockMaster created successfully!',
            'status' => 200,
            'data' => $data,
            'items' => $items,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
{
    $user = Auth::user();
    $uid = $user->id;
    $proId = $user->profile_id;

    // FETCH SINGLE STOCK MASTER
    $master = DB::table('stock_masters as sm')
        ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
        ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
        ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
        ->join('users as s', 'sm.stock_created_by', '=', 's.id')
        ->join('profiles as p', 's.profile_id', '=', 'p.id')
        ->select(
            'from_w.warehouse_name as from_warehouse_name',
            'to_w.warehouse_name as to_warehouse_name',
            's.username as created_by_name',
            'st.stock_type_name',
            'sm.*'
        )
        ->where('sm.stock_id', $id)
        ->where('sm.is_deleted', 0)
        ->where('sm.stock_created_by', $uid)
        ->where('p.id', $proId)
        ->first();

    if (!$master) {
        return response()->json([
            'message' => 'StockMaster not found!',
            'status'  => 404,
            'data'    => null
        ]);
    }
    // RETURN RESPONSE
    return response()->json([
        'message' => 'StockMaster fetched successfully',
        'status'  => 200,
        'data'    => [
            ...((array)$master),
            'items' => $this->detailService->stockDetail($id)
        ]
    ]);
}


    public function getStockByOrderNo(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        // Use first() to get a single record, not a query builder
        $stock_master = DB::table('stock_masters')
            ->where('stock_created_by', $uid)
            ->where('order_id', $id)
            ->where('is_deleted', 0)
            ->first();

        if (!$stock_master) {
            return response()->json([
                'message' => 'StockMaster not found!',
                'status' => 200,
                'data' => []
            ], 200);
        }

        $items = DB::table('stock_details')
            ->where('stock_id', $stock_master->stock_id)
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->where('stock_details.is_deleted', 0)
            ->select('items.item_name', 'items.item_code', 'stock_details.*')
            ->get();

        $data = array_merge((array)$stock_master, [
            'items' => $items
        ]);

        return response()->json([
            'message' => 'StockMaster show successfully!',
            'status' => 200,
            'data' => $data,
        ], 200);
    }


    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $stock_masters = StockMaster::find($id);
        $stock_date = now()->format('Y-m-d');

        if (!$stock_masters) {
            return response()->json([
                "message" => "This stock masters not found!",
            ], 404);
        }

        $validated = $request->validate([
            'stock_type_id' => 'required|integer',
            'from_warehouse' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'stock_date' => 'required|date',
            'stock_remark' => 'required|string|max:255',
            'items' => 'array||min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer',
            'items.*.expire_date' => 'required|date',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
        ]);
        $stock_masters->update([
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => $validated['from_warehouse'],
            'warehouse_id' => $validated['warehouse_id'],
            'stock_date' => $validated['stock_date'],
            'stock_remark' => $validated['stock_remark'],
            // 'stock_created_by'=> $validated['stock_created_by'],
        ]);


        $exchange_rate = ExchangeRate::find($proId);

        // ✅ Update the master record using the object, not query builder

        if ($stock_masters) {
            StockDetails::where('stock_id', $id)->delete();
        }
        $items = [];
        foreach ($validated['items'] as $item) {
            $items[] = StockDetails::create([
                'stock_id' => StockMaster::max('stock_id'),
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'],
                'expire_date' => $item['expire_date'],
                'transection_date' => $stock_date,
            ]);


        }
        return response()->json([
            "message" => "StockMaster updated successfully",
            "status" => 200,
            "data" => $stock_masters,
            "details" => $items,
        ], 200);
    }



    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $stockMaster = StockMaster::find($id);

        if (!$stockMaster) {
            return response()->json([
                'message' => 'This stock master not found!',
            ], 404);
        }

        // ✅ update all related stock details
        StockDetails::where('stock_id', $stockMaster->stock_id)
            ->update(['is_deleted' => 1]);

        // ✅ update stock master
        $stockMaster->is_deleted = 1;
        $stockMaster->save();

        return response()->json([
            'message' => 'StockMaster deleted successfully',
            'status' => 200,
            'data' => $stockMaster,
        ], 200);
    }

}
