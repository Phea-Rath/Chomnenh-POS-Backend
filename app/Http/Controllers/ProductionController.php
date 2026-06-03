<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\ProductionDetail;
use App\Models\Items;
use App\Models\RawMaterial;
use App\Models\StockDetails;
use App\Models\ExchangeRate;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ItemService;
use App\Services\DetailService;

class ProductionController extends Controller
{
    protected $itemService;
    protected $detailService;


    public function __construct( ItemService $itemService, DetailService $detailService)
    {
        $this->itemService = $itemService;
        $this->detailService = $detailService;
    }
    public function index(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->get('search');

        $query = DB::table('productions as prod')
            ->join('users as u', 'prod.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->join('items as i', 'prod.item_id', '=', 'i.item_id')
            ->select(
                'prod.*',
                'i.item_name',
                'i.item_code',
                'u.username as created_by_name',
                DB::raw('0 as image'),
                DB::raw('0 as images'),
            )
            ->where('prod.is_deleted', 0)
            // ->where('u.id', $uid)
            ->where('pr.id', $proId);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('prod.production_no', 'LIKE', '%' . $search . '%')
                  ->orWhere('i.item_name', 'LIKE', '%' . $search . '%')
                  ->orWhere('i.item_code', 'LIKE', '%' . $search . '%')
                  ->orWhere('u.username', 'LIKE', '%' . $search . '%');
            });
        }

        if ($request->filled('start_date')) {
            $query->whereDate('prod.production_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('prod.production_date', '<=', $request->end_date);
        }

        $productions = $query->orderBy('prod.id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);
        foreach($productions as $pro){
            $images = $this->itemService->getImage($pro->item_id);
            $pro->image = $images[0]??null;
            $pro->images = $images;
        }

        if ($productions->total() == 0) {
            return response()->json([
                'message' => 'No productions found!',
                'status' => 404,
                'data' => [],
                'pagination' => [
                    'current_page' => $productions->currentPage(),
                    'per_page' => $productions->perPage(),
                    'total' => $productions->total(),
                    'last_page' => $productions->lastPage(),
                ]
            ]);
        }

        // Convert paginated items to arrays
        $productionItems = collect($productions->items())->map(function ($p) {
            return (array)$p;
        })->toArray();

        $productionIds = array_column($productionItems, 'id');

        // Bulk fetch details with raw material info to avoid N+1 and produce clean arrays
        $detailsGrouped = [];
        if (!empty($productionIds)) {
            $detailRows = DB::table('production_details as pd')
                ->leftJoin('raw_materials as rm', 'pd.raw_material_id', '=', 'rm.id')
                ->select(
                    'pd.id',
                    'pd.production_id',
                    'pd.raw_material_id',
                    'rm.material_image',
                    'pd.quantity',
                    'pd.cost_per_unit',
                    'pd.total_cost',
                    'rm.material_name',
                    'rm.material_code'
                )
                ->whereIn('pd.production_id', $productionIds)
                ->where('pd.is_deleted', 0)
                ->get();

            $detailRows->each(function ($d) use (&$detailsGrouped) {
                $pid = $d->production_id;
                if (!isset($detailsGrouped[$pid])) {
                    $detailsGrouped[$pid] = [];
                }

                $detailsGrouped[$pid][] = [
                    'id' => $d->id,
                    'production_id' => $d->production_id,
                    'raw_material_id' => $d->raw_material_id,
                    'quantity' => $d->quantity,
                    'cost_per_unit' => $d->cost_per_unit,
                    'total_cost' => $d->total_cost,
                    'material_name' => $d->material_name,
                    'material_code' => $d->material_code,
                ];
            });
        }

        // Attach grouped details to productions and ensure arrays are clean
        $data = array_map(function ($prod) use ($detailsGrouped) {
            $prod['details'] = $detailsGrouped[$prod['id']] ?? [];
            // Keep only expected keys (optional cleanup)
            return $prod;
        }, $productionItems);

        return response()->json([
            'message' => 'Productions fetched successfully',
            'status' => 200,
            'data' => array_values($data),
            'pagination' => [
                'current_page' => $productions->currentPage(),
                'per_page' => $productions->perPage(),
                'total' => $productions->total(),
                'last_page' => $productions->lastPage(),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $exchange_rate = ExchangeRate::find($proId);

        $validated = $request->validate([
            'production_date' => 'required|date',
            'item_id' => 'required|integer|exists:items,item_id',
            'quantity' => 'required|integer|min:1',
            'total_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'raw_materials' => 'required|array|min:1',
            'raw_materials.*.raw_material_id' => 'required|integer|exists:raw_materials,id',
            'raw_materials.*.quantity' => 'required|numeric|min:0.01',
            'raw_materials.*.cost_per_unit' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
        ]);

        //check stock available or not
        $errors = [];
        foreach($validated['raw_materials'] as $material){
            $stock = $this->detailService->quanRaws($material['raw_material_id'])[0];
            if($stock->in_stock < $material['quantity']){
                $rawName = DB::table('raw_materials')->where('id', $material['raw_material_id'])->first();
                $errors[] = $rawName->material_name." is not available, Now we have ".$stock->in_stock.$rawName->primary_unit.", please check stock first!";
            }

        }
        if(!empty($errors)){
            return response()->json([
                'message' => $errors,
                'status' => 400,
            ]);
        }
        $now = now();

            $count = Production::join('users as u', 'productions.created_by', '=', 'u.id')
                ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
                ->where('pr.id', $proId)
                ->whereYear('productions.created_at', $now->year)
                ->whereMonth('productions.created_at', $now->month)
                ->count();
        $productionNo = 'PROD-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        DB::beginTransaction();
        try {
            $production = Production::create([
                'production_no' => $productionNo,
                'production_date' => $validated['production_date'],
                'item_id' => $validated['item_id'],
                'quantity' => $validated['quantity'],
                'total_cost' => $validated['total_cost'],
                'exchange_rate' => $exchange_rate->usd_to_khr ?? 4000,
                'created_by' => $uid,
            ]);

            $details = [];
            foreach ($validated['raw_materials'] as $material) {
                $totalMaterialCost = $material['quantity'] * $material['cost_per_unit'];

                $details[] = ProductionDetail::create([
                    'production_id' => $production->id,
                    'raw_material_id' => $material['raw_material_id'],
                    'quantity' => $material['quantity'],
                    'cost_per_unit' => $material['cost_per_unit'],
                    'total_cost' => $totalMaterialCost,
                    'created_by' => $uid,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Production created successfully!',
                'status' => 200,
                'data' => $production,
                'details' => $details
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating production: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }


    public function confirmStock(string $id){
        $production = Production::find($id);
        if (!$production || $production->is_deleted) {
            return response()->json([
                'message' => 'Production not found!',
                'status' => 404
            ]);
        }
        $production->update(['status' => 'confirmed']);
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $exchange_rate = ExchangeRate::find($proId);
        $stock_date = now()->format('Y-m-d');

            // Generate stock_no safely
            $maxStockId = DB::table('stock_masters')->max('stock_id');
            $newStockId = ($maxStockId ?? 0) + 1;
            $now = now();

            $count = StockMaster::join('users as u', 'stock_masters.stock_created_by', '=', 'u.id')
                ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
                ->where('pr.id', $proId)
                ->whereYear('stock_masters.created_at', $now->year)
                ->whereMonth('stock_masters.created_at', $now->month)
                ->count();
            $stock_no = 'IN-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            // Create stock master
            DB::table('stock_masters')->insert([
                'stock_id' => $newStockId,
                'stock_no' => $stock_no,
                'stock_type_id' => 2, // 2 = stock in
                'from_warehouse' => 2, // Default or set as needed
                'warehouse_id' => 1, // Default or set as needed
                'stock_date' => $stock_date,
                'quantity' => $production->quantity,
                'stock_remark' => 'Production Completed from No: ' . $production->production_no,
                'stock_created_by' => $uid,
                'exchange_rate' => $exchange_rate->usd_to_khr ?? 4000,
                'is_deleted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stockMasterId = $newStockId;
            $item = Items::find($production->item_id);

            $exchange_rate = ExchangeRate::find($proId);
            $stockItems = StockDetails::create([
                'stock_id' => (int)$stockMasterId,
                'item_id' => $item->item_id,
                'quantity' => (int)$production->quantity,
                'item_cost' => (float)$production->total_cost / (int)$production->quantity ?? 0,
                'expire_date' => null, // Set if available
                'transection_date' => $stock_date,
                'is_deleted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function show($id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        // $proId = $user->profile_id;

        $production = DB::table('productions as prod')
            ->join('users as u', 'prod.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->join('items as i', 'prod.item_id', '=', 'i.item_id')
            ->select(
                'prod.*',
                'i.item_name',
                'i.item_code',
                'u.username as created_by_name'
            )
            ->where('prod.id', $id)
            ->where('prod.is_deleted', 0)
            // ->where('u.id', $uid)
            // ->where('pr.id', $proId)
            ->first();

        if (!$production) {
            return response()->json([
                'message' => 'Production not found!',
                'status' => 404,
                'data' => []
            ]);
        }

        $details = DB::table('production_details as pd')
                ->leftJoin('raw_materials as rm', 'pd.raw_material_id', '=', 'rm.id')
                ->select(
                    'pd.id',
                    'pd.production_id',
                    'pd.raw_material_id',
                    'rm.material_image',
                    'pd.quantity',
                    'pd.cost_per_unit',
                    'pd.total_cost',
                    'rm.material_name',
                    'rm.material_code'
                )
                ->where('pd.production_id', $id)
                ->where('pd.is_deleted', 0)
                ->get();

        $data = array_merge(
            (array)$production,
            ['details' => $details]
        );

        return response()->json([
            'message' => 'Production fetched successfully!',
            'status' => 200,
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $uid = $user->id;

        $production = Production::find($id);

        if (!$production || $production->is_deleted) {
            return response()->json([
                'message' => 'Production not found!',
                'status' => 404
            ]);
        }

        $validated = $request->validate([
            'production_date' => 'required|date',
            'item_id' => 'required|integer|exists:items,item_id',
            'quantity' => 'required|integer|min:1',
            'total_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'raw_materials' => 'required|array|min:1',
            'raw_materials.*.raw_material_id' => 'required|integer|exists:raw_materials,id',
            'raw_materials.*.quantity' => 'required|numeric|min:0.01',
            'raw_materials.*.cost_per_unit' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
        ]);

        DB::beginTransaction();
        try {
            $production->update([
                'production_date' => $validated['production_date'],
                'item_id' => $validated['item_id'],
                'quantity' => $validated['quantity'],
                'total_cost' => $validated['total_cost'],
            ]);

            // Delete existing details
            ProductionDetail::where('production_id', $id)->delete();

            // Create new details
            $details = [];
            foreach ($validated['raw_materials'] as $material) {
                $totalMaterialCost = $material['quantity'] * $material['cost_per_unit'];

                $details[] = ProductionDetail::create([
                    'production_id' => $id,
                    'raw_material_id' => $material['raw_material_id'],
                    'quantity' => $material['quantity'],
                    'cost_per_unit' => $material['cost_per_unit'],
                    'total_cost' => $totalMaterialCost,
                    'created_by' => $uid,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Production updated successfully',
                'status' => 200,
                'data' => $production,
                'details' => $details
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error updating production: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function destroy($id)
    {
        $production = Production::find($id);

        if (!$production) {
            return response()->json([
                'message' => 'Production not found!',
                'status' => 404
            ]);
        }

        DB::beginTransaction();
        try {
            $production->update(['is_deleted' => 1]);
            ProductionDetail::where('production_id', $id)->update(['is_deleted' => 1]);

            DB::commit();

            return response()->json([
                'message' => 'Production deleted successfully!',
                'status' => 200
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error deleting production: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }
}
