<?php

namespace App\Http\Controllers;

use App\Events\OnlineEvent;
use App\Models\StockDetails;
use App\Models\StockRawDetail;
use App\Models\StockMaster;
use App\Models\Items;
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

    public function indexDataMobile(Request $request, bool $isRaw = false)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = DB::table('stock_masters as sm')
            ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
            ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
            ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
            ->join('users as s', 'sm.stock_created_by', '=', 's.id')
            ->join('profiles as p', 's.profile_id', '=', 'p.id')
            ->where('sm.is_deleted', 0)
            ->where('p.id', $proId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sm.stock_no', 'LIKE', "%{$search}%")
                    ->orWhere('from_w.warehouse_name', 'LIKE', "%{$search}%")
                    ->orWhere('to_w.warehouse_name', 'LIKE', "%{$search}%")
                    ->orWhere('st.stock_type_name', 'LIKE', "%{$search}%")
                    ->orWhere('s.username', 'LIKE', "%{$search}%");
            });
        }

        $rawStocks = $query->select('sm.stock_id')
            ->orderBy('sm.stock_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawStocks->total() === 0) {
            return response()->json([
                'message' => 'Stock not found!',
                'status' => 404,
                'data' => [],
            ], 404);
        }

        $stocks = [];
        foreach ($rawStocks as $stock) {
            $formatted = $this->formatMobileStock((int) $stock->stock_id, $proId, $isRaw);
            if ($formatted) {
                $stocks[] = $formatted;
            }
        }

        return response()->json([
            'message' => 'Stock selected successfully',
            'status' => 200,
            'data' => $stocks,
            'pagination' => [
                'current_page' => $rawStocks->currentPage(),
                'per_page' => $rawStocks->perPage(),
                'total' => $rawStocks->total(),
                'last_page' => $rawStocks->lastPage(),
            ],
        ]);
    }
    public function showDataMobile($id, bool $isRaw = false)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $data = $this->formatMobileStock((int) $id, $proId, $isRaw);

        if (!$data) {
            return response()->json([
                'message' => 'Stock not found!',
                'status' => 404,
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Stock selected successfully',
            'status' => 200,
            'data' => $data,
        ]);
    }

    private function    formatMobileStock(int $stockId, int $profileId, bool $isRaw = false): ?array
    {
        $stock = DB::table('stock_masters as sm')
            ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
            ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
            ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
            ->join('users as s', 'sm.stock_created_by', '=', 's.id')
            ->join('profiles as p', 's.profile_id', '=', 'p.id')
            ->where('sm.stock_id', $stockId)
            ->where('sm.is_deleted', 0)
            ->where('p.id', $profileId)
            ->select(
                'sm.stock_id',
                'sm.stock_no',
                'sm.stock_date',
                'sm.created_at',
                'sm.from_warehouse',
                'sm.warehouse_id',
                'sm.stock_type_id',
                'from_w.warehouse_name as from_warehouse_name',
                'to_w.warehouse_name as to_warehouse_name',
                'st.stock_type_name',
                's.username as created_by_name'
            )
            ->first();

        if (!$stock) {
            return null;
        }
        $items = [];

        if ($isRaw) {
            $items = DB::table('stock_raw_details as srd')
                ->join('raw_materials as rm', 'srd.raw_material_id', '=', 'rm.id')
                ->where('srd.stock_id', $stockId)
                ->where('srd.is_deleted', 0)
                ->select(
                    'srd.raw_material_id as item_id',
                    'rm.material_name as item_name',
                    'srd.quantity'
                )
                ->orderBy('srd.id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'item_id' => (int) $item->item_id,
                        'name' => $item->item_name,
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->toArray();
        } else {

            $items = DB::table('stock_details as sd')
                ->join('items as i', 'sd.item_id', '=', 'i.item_id')
                ->where('sd.stock_id', $stockId)
                ->where('sd.is_deleted', 0)
                ->select(
                    'sd.item_id',
                    'i.item_name',
                    'sd.quantity'
                )
                ->orderBy('sd.detail_id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'item_id' => (int) $item->item_id,
                        'name' => $item->item_name,
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->toArray();
        }



        return [
            'stock_id' => (int) $stock->stock_id,
            'stock_no' => $stock->stock_no,
            'from_warehouse' => [
                'id' => (int) $stock->from_warehouse,
                'name' => $stock->from_warehouse_name,
            ],
            'to_warehouse' => [
                'id' => (int) $stock->warehouse_id,
                'name' => $stock->to_warehouse_name,
            ],
            'stock_type' => [
                'id' => (int) $stock->stock_type_id,
                'name' => strtoupper($stock->stock_type_name),
            ],
            'items' => $items,
            'created_by_name' => $stock->created_by_name,
            'stock_date' => $stock->created_at ?? $stock->stock_date,
        ];
    }


    public function indexMobile(Request $request)
    {
        return $this->indexDataMobile($request, false);
    }
    public function indexRawMobile(Request $request)
    {
        return $this->indexDataMobile($request, true);
    }
    public function showMobile($id)
    {
        return $this->showDataMobile($id, false);
    }
    public function showRawMobile($id)
    {
        return $this->showDataMobile($id, true);
    }


    public function index(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search');

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
            ->whereNotIn('to_w.warehouse_id', [2, 3, 4, 5])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sm.stock_no', 'like', "%{$search}%")
                        ->orWhere('from_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('to_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('st.stock_type_name', 'like', "%{$search}%")
                        ->orWhere('s.username', 'like', "%{$search}%");
                });
            })
            ->orderBy('sm.stock_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // BUILD DATA RESULT
        $data = $stock_masters->getCollection()->map(function ($master) {
            return [
                ...((array)$master),
                'items' => $this->detailService->stockDetail($master->stock_id)
            ];
        });

        return response()->json([
            'message' => $stock_masters->count() > 0 ? 'StockMaster selected successfully' : 'StockMaster not found!',
            'status'  => $stock_masters->count() > 0 ? 200 : 404,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page'     => $stock_masters->perPage(),
                'total'        => $stock_masters->total(),
                'last_page'    => $stock_masters->lastPage(),
            ],
        ]);
    }

    public function indexRaw(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search');

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
            ->whereNotIn('to_w.warehouse_id', [1, 2, 3, 4])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sm.stock_no', 'like', "%{$search}%")
                        ->orWhere('from_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('to_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('st.stock_type_name', 'like', "%{$search}%")
                        ->orWhere('s.username', 'like', "%{$search}%");
                });
            })
            ->orderBy('sm.stock_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // BUILD DATA RESULT
        $data = $stock_masters->getCollection()->map(function ($master) {
            return [
                ...((array)$master),
                'items' => $this->detailService->stockRawDetail($master->stock_id),
            ];
        });

        return response()->json([
            'message' => $stock_masters->count() > 0 ? 'StockMaster selected successfully' : 'StockMaster not found!',
            'status'  => $stock_masters->count() > 0 ? 200 : 404,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page'     => $stock_masters->perPage(),
                'total'        => $stock_masters->total(),
                'last_page'    => $stock_masters->lastPage(),
            ],
        ]);
    }


    public function popularStockIn(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 5);

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $popular = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sm.stock_type_id', 2) // stock_in
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('p.id', $proId);

        if ($request->filled('user_id')) {
            $popular = $popular->where('sm.stock_created_by', $request->user_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $popular = $popular->whereBetween('sm.stock_date', [$request->start_date, $request->end_date]);
        }

        $popular = $popular->select(
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
        $user  = Auth::user();
        $uid   = $user->id;
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

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
            // ->where('sm.stock_created_by', $uid)

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sm.stock_no', 'like', "%{$search}%")
                        ->orWhere('from_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('to_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('st.stock_type_name', 'like', "%{$search}%")
                        ->orWhere('s.username', 'like', "%{$search}%");
                });
            })

            ->orderBy('sm.stock_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        if ($stock_masters->count() == 0) {
            return response()->json([
                'message' => 'StockMaster not found!',
                'status'  => 404,
                'data'    => [],
            ]);
        }

        // BUILD DATA RESULT
        $data = $stock_masters->getCollection()->map(function ($master) {
            return [
                ...((array) $master),
                'items' => $this->detailService->stockDetail($master->stock_id),
            ];
        });

        return response()->json([
            'message' => 'StockMaster pagination selected successfully',
            'status'  => 200,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page'     => $stock_masters->perPage(),
                'total'        => $stock_masters->total(),
                'last_page'    => $stock_masters->lastPage(),
            ],
        ]);
    }


    public function stockTransection(Request $request)
    {
        $user  = Auth::user();
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

        $stock_masters = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('warehouses as wh_from', 'sm.from_warehouse', '=', 'wh_from.warehouse_id')
            ->join('warehouses as wh_to', 'sm.warehouse_id', '=', 'wh_to.warehouse_id')
            ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
            ->join('users as s', 'sm.stock_created_by', '=', 's.id')
            ->join('profiles as p', 's.profile_id', '=', 'p.id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('categories as c', 'i.category_id', '=', 'c.category_id')
            ->join('brands as b', 'i.brand_id', '=', 'b.brand_id')
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
                'wh_from.warehouse_name as from_warehouse_name',
                'wh_to.warehouse_name as to_warehouse_name',
                DB::raw('0 as images'),
                DB::raw('0 as image'),
                DB::raw('SUM(sd.quantity) as quantity'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_sale'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste')
            )
            ->where('p.id', $proId)
            ->where('sd.is_deleted', 0)

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('i.item_code', 'like', "%{$search}%")
                        ->orWhere('i.item_name', 'like', "%{$search}%")
                        ->orWhere('i.barcode', 'like', "%{$search}%")
                        ->orWhere('c.category_name', 'like', "%{$search}%")
                        ->orWhere('b.brand_name', 'like', "%{$search}%")
                        ->orWhere('wh_from.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('wh_to.warehouse_name', 'like', "%{$search}%");
                });
            })

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
                'wh_from.warehouse_name',
                'wh_to.warehouse_name'
            )
            ->orderBy('i.item_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        foreach ($stock_masters as $stock_master) {
            $imagelist = $this->itemService->getImage($stock_master->item_id);
            $stock_master->images = !empty($imagelist) ? $imagelist : null;
            $stock_master->image  = !empty($imagelist) ? $imagelist[0]['image'] : null;
        }

        if ($stock_masters->isEmpty()) {
            return response()->json([
                'message' => 'No item stock summary found!',
                'status'  => 200,
                'data'    => []
            ]);
        }

        return response()->json([
            'message' => 'StockMaster summary selected successfully',
            'status'  => 200,
            'data'    => $stock_masters->items(),
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page'     => $stock_masters->perPage(),
                'total'        => $stock_masters->total(),
                'last_page'    => $stock_masters->lastPage(),
            ]
        ]);
    }

    public function stockTransferMobile(Request $request)
    {
        return $this->stockTransferDataMobile($request, false);
    }

    public function stockTransferDataMobile(Request $request, bool $isRaw = false)
    {
        $user  = Auth::user();
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword
        $query = DB::table('stock_masters as sm')
            ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
            ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
            ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
            ->join('users as s', 'sm.stock_created_by', '=', 's.id')
            ->join('profiles as p', 's.profile_id', '=', 'p.id')
            ->where('sm.is_deleted', 0)
            ->whereNotIn('sm.from_warehouse', [2, 3, 4])
            ->whereNotIn('sm.warehouse_id', [2, 3, 4])
            ->where('p.id', $proId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('sm.stock_no', 'LIKE', "%{$search}%")
                    ->orWhere('from_w.warehouse_name', 'LIKE', "%{$search}%")
                    ->orWhere('to_w.warehouse_name', 'LIKE', "%{$search}%")
                    ->orWhere('st.stock_type_name', 'LIKE', "%{$search}%")
                    ->orWhere('s.username', 'LIKE', "%{$search}%");
            });
        }

        $rawStocks = $query->select('sm.stock_id')
            ->orderBy('sm.stock_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawStocks->total() === 0) {
            return response()->json([
                'message' => 'Stock not found!',
                'status' => 404,
                'data' => [],
            ], 404);
        }

        $stocks = [];
        foreach ($rawStocks as $stock) {
            $formatted = $this->formatMobileStockTransfer((int) $stock->stock_id, $proId, $isRaw);
            if ($formatted) {
                $stocks[] = $formatted;
            }
        }

        return response()->json([
            'message' => 'Stock selected successfully',
            'status' => 200,
            'data' => $stocks,
            'pagination' => [
                'current_page' => $rawStocks->currentPage(),
                'per_page' => $rawStocks->perPage(),
                'total' => $rawStocks->total(),
                'last_page' => $rawStocks->lastPage(),
            ],
        ]);
    }

    private function formatMobileStockTransfer(int $stockId, int $profileId, bool $isRaw = false): ?array
    {
        $stock = DB::table('stock_masters as sm')
            ->join('warehouses as from_w', 'sm.from_warehouse', '=', 'from_w.warehouse_id')
            ->join('warehouses as to_w', 'sm.warehouse_id', '=', 'to_w.warehouse_id')
            ->join('stock_types as st', 'sm.stock_type_id', '=', 'st.stock_type_id')
            ->join('users as s', 'sm.stock_created_by', '=', 's.id')
            ->join('profiles as p', 's.profile_id', '=', 'p.id')
            ->where('sm.stock_id', $stockId)
            ->where('sm.is_deleted', 0)
            ->where('p.id', $profileId)
            ->whereNotIn('sm.from_warehouse', [2, 3, 4])
            ->whereNotIn('sm.warehouse_id', [2, 3, 4])
            ->select(
                'sm.stock_id',
                'sm.stock_no',
                'sm.stock_date',
                'sm.created_at',
                'sm.from_warehouse',
                'sm.warehouse_id',
                'sm.stock_type_id',
                'from_w.warehouse_name as from_warehouse_name',
                'to_w.warehouse_name as to_warehouse_name',
                'st.stock_type_name',
                's.username as created_by_name'
            )
            ->first();

        if (!$stock) {
            return null;
        }
        $items = [];

        if ($isRaw) {
            $items = DB::table('stock_raw_details as srd')
                ->join('raw_materials as rm', 'srd.raw_material_id', '=', 'rm.id')
                ->where('srd.stock_id', $stockId)
                ->where('srd.is_deleted', 0)
                ->select(
                    'srd.raw_material_id as item_id',
                    'rm.material_name as item_name',
                    'srd.quantity'
                )
                ->orderBy('srd.id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'item_id' => (int) $item->item_id,
                        'name' => $item->item_name,
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->toArray();
        } else {

            $items = DB::table('stock_details as sd')
                ->join('items as i', 'sd.item_id', '=', 'i.item_id')
                ->where('sd.stock_id', $stockId)
                ->where('sd.is_deleted', 0)
                ->select(
                    'sd.item_id',
                    'i.item_name',
                    'sd.quantity'
                )
                ->orderBy('sd.detail_id', 'asc')
                ->get()
                ->map(function ($item) {
                    return [
                        'item_id' => (int) $item->item_id,
                        'name' => $item->item_name,
                        'quantity' => (int) $item->quantity,
                    ];
                })
                ->values()
                ->toArray();
        }

        return [
            'stock_id' => (int) $stock->stock_id,
            'stock_no' => $stock->stock_no,
            'from_warehouse' => [
                'id' => (int) $stock->from_warehouse,
                'name' => $stock->from_warehouse_name,
            ],
            'to_warehouse' => [
                'id' => (int) $stock->warehouse_id,
                'name' => $stock->to_warehouse_name,
            ],
            'stock_type' => [
                'id' => (int) $stock->stock_type_id,
                'name' => strtoupper($stock->stock_type_name),
            ],
            'items' => $items,
            'created_by_name' => $stock->created_by_name,
            'stock_date' => $stock->created_at ?? $stock->stock_date,
        ];
    }

    public function stockTransfer(Request $request)
    {
        $user  = Auth::user();
        $uid   = $user->id;
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

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
            // ->whereNotIn('sm.from_warehouse', [2, 3, 4])
            ->whereNotIn('sm.warehouse_id', [1, 2, 3, 4, 5])
            ->where('sm.is_deleted', 0)
            // ->where('sm.stock_created_by', $uid)

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sm.stock_no', 'like', "%{$search}%")
                        ->orWhere('from_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('to_w.warehouse_name', 'like', "%{$search}%")
                        ->orWhere('st.stock_type_name', 'like', "%{$search}%")
                        ->orWhere('s.username', 'like', "%{$search}%");
                });
            })

            ->orderBy('sm.stock_id', 'desc')
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
                ...((array) $master),
                'items' => $this->detailService->stockDetail($master->stock_id)
            ];
        });

        return response()->json([
            'message' => 'StockMaster pagination selected successfully',
            'status'  => 200,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $stock_masters->currentPage(),
                'per_page'     => $stock_masters->perPage(),
                'total'        => $stock_masters->total(),
                'last_page'    => $stock_masters->lastPage(),
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
            ->whereNotIn('sm.from_warehouse', [3, 4])
            ->whereNotIn('sm.warehouse_id', [2, 3, 4])
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
                // DB::raw('0 as images'),
                DB::raw('0 as image'),
                DB::raw('0 as stock'),
                // 'sm.created_at'
            )
            ->where('p.id', $proId)
            ->where('sd.is_deleted', 0)
            ->whereIn('sm.stock_type_id', [1, 2]) // stock in
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
            // $stock_master->images = !empty($imagelist) ? $imagelist : null;
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
        $stock_date = now()->format('Y-m-d');
        $validated = $request->validate([
            'stock_type_id' => 'required|integer',
            // 'from_warehouse' => 'required|integer',
            'warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
            'stock_remark' => 'nullable|string|max:255',
            'items' => 'array||min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.quantity' => 'required|integer',
            'items.*.item_cost' => 'required|numeric',
            'items.*.expire_date' => 'required|date',
        ]);

        $now = now();
        $type = $validated['stock_type_id'] == 1 ? 'RETURN' : ($validated['stock_type_id'] == 2 ? 'IN' : ($validated['stock_type_id'] == 3 ? 'OUT' : ($validated['stock_type_id'] == 4 ? 'WASTE' : 'OTHER')));

        $count = StockMaster::join('users as u', 'stock_masters.stock_created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->where('pr.id', $proId)
            ->whereYear('stock_masters.created_at', $now->year)
            ->whereMonth('stock_masters.created_at', $now->month)
            ->count();
        $stock_no = $type . '-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        // if($validated['from_warehouse'] == 1||$validated['from_warehouse'] == 5){
        //     if($validated['stock_type_id'] != 3){
        //         return response()->json([
        //             'message'=>'Raw Material Warehouse can to use stock  only "stock out"',
        //         ],200);
        //     }
        // }
        if ($validated['warehouse_id'] == 1 || $validated['warehouse_id'] == 5) {
            if ($validated['stock_type_id'] != 2 && $validated['stock_type_id'] != 1) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock in"',
                ], 200);
            }
        }

        $exchange_rate = ExchangeRate::find($proId);
        // Create the post
        $data = StockMaster::create([
            'stock_no' => $stock_no,
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => $validated['from_warehouse'] ?? 2,
            'warehouse_id' => $validated['warehouse_id'],
            'quantity' => array_sum(array_column($validated['items'], 'quantity')),
            'stock_date' => $stock_date,
            'stock_remark' => $validated['stock_remark'],
            'exchange_rate' => $exchange_rate->usd_to_khr ?? 4000,
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

            Items::find($item['item_id'])->update([
                'cost_price' => $item['item_cost'],
            ]);
        }

        broadcast(new OnlineEvent('stock', $proId))->toOthers();
        return response()->json([
            'message' => 'StockMaster created successfully!',
            'status' => 200,
        ], 201);
    }



    public function storeRaw(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $stock_date = now()->format('Y-m-d');
        $validated = $request->validate([
            'stock_type_id' => 'required|integer',
            // 'from_warehouse' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'stock_remark' => 'nullable|string|max:255',
            'exchange_rate' => 'nullable|numeric',
            'items' => 'array||min:1',
            'items.*.raw_material_id' => 'required|integer',
            'items.*.quantity' => 'required|integer',
            'items.*.item_cost' => 'required|numeric',
            'items.*.expire_date' => 'required|date',
        ]);

        $now = now();
        $type = $validated['stock_type_id'] == 1 ? 'RRETURN' : ($validated['stock_type_id'] == 2 ? 'RIN' : ($validated['stock_type_id'] == 3 ? 'ROUT' : ($validated['stock_type_id'] == 4 ? 'RWASTE' : 'ROTHER')));

        $count = StockMaster::join('users as u', 'stock_masters.stock_created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->where('pr.id', $proId)
            ->whereYear('stock_masters.created_at', $now->year)
            ->whereMonth('stock_masters.created_at', $now->month)
            ->count();
        $stock_no = $type . '-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        // if($validated['from_warehouse'] == 1||$validated['from_warehouse'] == 5){
        //     if($validated['stock_type_id'] != 3){
        //         return response()->json([
        //             'message'=>'Raw Material Warehouse can to use stock  only "stock out"',
        //         ],200);
        //     }
        // }
        if ($validated['warehouse_id'] == 1 || $validated['warehouse_id'] == 5) {
            if ($validated['stock_type_id'] != 2 && $validated['stock_type_id'] != 1) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock in"',
                ], 200);
            }
        }



        $exchange_rate = ExchangeRate::find($proId);
        // Create the post
        $data = StockMaster::create([
            'stock_no' => $stock_no,
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => 2,
            'warehouse_id' => $validated['warehouse_id'],
            'quantity' => array_sum(array_column($validated['items'], 'quantity')),
            'stock_date' => $stock_date,
            'stock_remark' => $validated['stock_remark'],
            'exchange_rate' => $validated['exchange_rate'] ?? $exchange_rate->usd_to_khr,
            'stock_created_by' => $uid,
        ]);
        $items = [];
        foreach ($validated['items'] as $item) {
            // $attr = json_encode($item['attributes']);


            $items[] = StockRawDetail::create([
                'stock_id' => StockMaster::max('stock_id'),
                'raw_material_id' => $item['raw_material_id'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'],
                'transection_date' => $stock_date,
                'expire_date' => $item['expire_date'],
            ]);
        }

        return response()->json([
            'message' => 'StockMaster created successfully!',
            'status' => 200,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        // $proId = $user->profile_id;

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
            // ->where('p.id', $proId)
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
    public function showRaw($id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        // $proId = $user->profile_id;

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
            // ->where('p.id', $proId)
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
                'items' => $this->detailService->stockRawDetail($id)
            ]
        ]);
    }


    public function getStockByOrderNo(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        // Use first() to get a single record, not a query builder
        $stock_master = DB::table('stock_masters')
            // ->where('stock_created_by', $uid)
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
            'warehouse_id' => 'required|integer|exists:warehouses,warehouse_id',
            'stock_date' => 'required|date',
            'stock_remark' => 'nullable|string|max:255',
            'items' => 'array||min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.quantity' => 'required|integer',
            'items.*.expire_date' => 'required|date',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
        ]);

        if ($validated['from_warehouse'] == 1 || $validated['from_warehouse'] == 5) {
            if ($validated['stock_type_id'] != 3) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock out"',
                ], 200);
            }
        }
        if ($validated['warehouse_id'] == 1 || $validated['warehouse_id'] == 5) {
            if ($validated['stock_type_id'] != 2 && $validated['stock_type_id'] != 1) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock in"',
                ], 200);
            }
        }

        $stock_masters->update([
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => $validated['from_warehouse'],
            'warehouse_id' => $validated['warehouse_id'],
            'stock_date' => $validated['stock_date'],
            'quantity' => array_sum(array_column($validated['items'], 'quantity')),
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
                'stock_id' => $id,
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
        ], 200);
    }



    public function updateRaw(Request $request, string $id)
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
            'exchange_rate' => 'nullable|numeric',
            'items' => 'array||min:1',
            'items.*.raw_material_id' => 'required|integer',
            'items.*.quantity' => 'required|integer',
            'items.*.expire_date' => 'required|date',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
        ]);

        if ($validated['from_warehouse'] == 1 || $validated['from_warehouse'] == 5) {
            if ($validated['stock_type_id'] != 3) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock out"',
                ], 200);
            }
        }
        if ($validated['warehouse_id'] == 1 || $validated['warehouse_id'] == 5) {
            if ($validated['stock_type_id'] != 2 && $validated['stock_type_id'] != 1) {
                return response()->json([
                    'message' => 'Raw Material Warehouse can to use stock  only "stock in"',
                ], 200);
            }
        }



        $stock_masters->update([
            'stock_type_id' => $validated['stock_type_id'],
            'from_warehouse' => $validated['from_warehouse'],
            'warehouse_id' => $validated['warehouse_id'],
            'stock_date' => $validated['stock_date'],
            'quantity' => array_sum(array_column($validated['items'], 'quantity')),
            'stock_remark' => $validated['stock_remark'],
        ]);


        // $exchange_rate = ExchangeRate::find($proId);

        // ✅ Update the master record using the object, not query builder

        if ($stock_masters) {
            StockRawDetail::where('stock_id', $id)->delete();
        }
        $items = [];
        foreach ($validated['items'] as $item) {
            $items[] = StockRawDetail::create([
                'stock_id' => $id,
                'raw_material_id' => $item['raw_material_id'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'],
                'expire_date' => $item['expire_date'],
                'transection_date' => $stock_date,
            ]);
        }
        return response()->json([
            "message" => "StockMaster updated successfully",
            "status" => 200,
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
        ], 200);
    }
    public function destroyRaw(string $id)
    {
        $stockMaster = StockMaster::find($id);

        if (!$stockMaster) {
            return response()->json([
                'message' => 'This stock master not found!',
            ], 404);
        }

        // ✅ update all related stock details
        StockRawDetail::where('stock_id', $stockMaster->stock_id)
            ->update(['is_deleted' => 1]);

        // ✅ update stock master
        $stockMaster->is_deleted = 1;
        $stockMaster->save();

        return response()->json([
            'message' => 'StockMaster deleted successfully',
            'status' => 200,
        ], 200);
    }
}
