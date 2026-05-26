<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\StockMaster;
use App\Models\ExchangeRate;
use App\Models\OrderMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ItemService;
use App\Services\AttributeService;
use App\Services\DetailService;


class ReportController extends Controller
{
    protected $attributeService;
    protected $itemService;
    protected $detailService;


    public function __construct(AttributeService $attributeService, ItemService $itemService,DetailService $detailService)
    {
        $this->attributeService = $attributeService;
        $this->itemService = $itemService;
        $this->detailService = $detailService;
    }

    public function saleReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $request->validate([
            'order_customer' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        // dd($request->start_date);

        $query = DB::table('order_items as oi')
            ->select(
                'om.order_no',
                'om.order_tel',
                'om.order_date',
                'c.customer_name as order_customer',
                'om.order_subtotal',
                'om.order_discount',
                'om.delivery_fee',
                'om.order_total',
                'om.payment',
                'om.balance'
            )
            ->join('order_masters as om', 'om.order_id', '=', 'oi.order_id')
            ->join('customers as c','c.customer_id','=','om.order_customer_id')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->where('om.is_deleted', 0)
            ->groupBy(
                'om.order_no',
                'om.order_tel',
                'om.order_date',
                'c.customer_name',
                'om.order_subtotal',
                'om.order_discount',
                'om.delivery_fee',
                'om.order_total',
                'om.payment',
                'om.balance'
            );

        // Filter by customer if provided
        if ($request->filled('order_customer')) {
            $query->where('c.customer_id', $request->order_customer);
        }

        if ($request->filled('user_id')) {
            $query->where('om.created_by', $request->user_id);
        }

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('om.order_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('om.order_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('om.order_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'sales report get successfully',
            'status' => 200,
            'data' => $results
        ]);
    }

    public function stockReportByRaw(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'created_by' => 'nullable|integer|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'stock_type_id' => 'nullable|integer|exists:stock_types,stock_type_id',
            'raw_material_id' => 'nullable|integer|exists:raw_materials,id',
        ]);

        $query = DB::table('stock_raw_details as srd')
            ->select(
                'rm.id as raw_material_id',
                'rm.material_code',
                'rm.material_name',
                'rm.primary_unit',
                'rm.secondary_unit',
                DB::raw('SUM(srd.quantity) as quantity'),
            )
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'srd.stock_id')
            ->join('raw_materials as rm', 'rm.id', '=', 'srd.raw_material_id')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->where('sm.is_deleted', 0)
            ->where('srd.is_deleted', 0)
            ->where('pf.id', $proId)
            ->groupBy(
                'rm.id',
                'rm.material_code',
                'rm.material_name',
                'rm.primary_unit',
                'rm.secondary_unit'
            );

        if ($request->filled('created_by')) {
            $query->where('sm.stock_created_by', $request->created_by);
        }
        if ($request->filled('stock_type_id')) {
            $query->where('sm.stock_type_id', $request->stock_type_id);
        }

        if ($request->filled('raw_material_id')) {
            $query->where('rm.id', $request->raw_material_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sm.stock_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('sm.stock_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('sm.stock_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'stock report by raw material retrieved successfully',
            'status' => 200,
            'data' => $results
        ], 200);
    }

    public function saleReportByItem(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'order_customer' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = \DB::table('order_items as oi')
            ->select(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                DB::raw('SUM(oi.quantity) as quantity'),
                'i.item_price',
                'cg.category_name',
                'br.brand_name',
                DB::raw('SUM(oi.price * oi.quantity) AS total_price'),
                DB::raw('SUM(om.order_discount) as order_discount'),
                // 'om.order_date',
                // 'c.customer_name as order_customer'
            )
            ->join('order_masters as om', 'om.order_id', '=', 'oi.order_id')
            ->join('customers as c','c.customer_id','=','om.order_customer_id')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->join('items as i', 'oi.item_id', '=', 'i.item_id')
            ->join('categories as cg', 'i.category_id', '=', 'cg.category_id')
            ->join('brands as br', 'i.brand_id', '=', 'br.brand_id')
            ->join('scales as sc', 'i.scale_id', '=', 'sc.scale_id')
            ->where('p.id', $proId)
            ->groupBy(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_price',
                'cg.category_name',
                'br.brand_name',
                );

        // Filter by customer if provided
        if ($request->filled('order_customer')) {
            $query->where('c.customer_id', $request->order_customer);
        }

        if ($request->filled('user_id')) {
            $query->where('om.created_by', $request->user_id);
        }
        // Filter by item name if provided
        if ($request->filled('item_id')) {
            $query->where('i.item_id', $request->item_id);
        }

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('om.order_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('om.order_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('om.order_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'sales report by item get successfully',
            'status' => 200,
            'data' => $results
        ]);
    }


    public function expenseReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'expense_by' => 'nullable|integer',
            'expense_type_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = \DB::table('expense_items as ei')
            ->select(
                'em.expense_no',
                'em.expense_date',
                'em.expense_by',
                'em.expense_supplier',
                'et.expense_type_name',
                'ei.quantity',
                'ei.unit_price',
                'ei.sub_total',
                'em.created_by'
            )
            ->join('expense_masters as em', 'ei.expense_id', '=', 'em.expense_id')
            ->join('expense_types as et', 'ei.expense_type_id', '=', 'et.expense_type_id')
            ->join('users as u', 'u.id', '=', 'em.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId);

        // Filter by expense_by if provided
        if ($request->filled('expense_by')) {
            $query->where('em.created_by', $request->expense_by);
        }

        // Filter by expense_type_id if provided
        if ($request->filled('expense_type_id')) {
            $query->where('ei.expense_type_id', $request->expense_type_id);
        }

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('em.expense_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('em.expense_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('em.expense_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'expense report get successfully',
            'status' => 200,
            'data' => $results
        ]);
    }

    public function purchaseReportByItem(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $request->validate([
            'created_by' => 'nullable|integer',
            'supplier_id' => 'nullable|integer|exists:suppliers,supplier_id',
            'item_type' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $results = collect();
        if($request->filled('item_type') && $request->item_type == 1){
            $query = DB::table('purchase_raw_details as pd')
                ->select(
                    'i.id as item_id',
                    'i.material_code as barcode',
                    'i.material_name as item_name',
                    DB::raw('
                    (
                        SELECT pd2.item_cost
                        FROM purchase_raw_details pd2
                        JOIN purchases p2 ON p2.purchase_id = pd2.purchase_id
                        WHERE pd2.raw_material_id = i.id
                        AND p2.is_deleted = 0
                        ORDER BY pd2.id DESC
                        LIMIT 1
                    ) as item_price
                    '),
                    DB::raw('SUM(pd.item_cost) as total_cost'),
                    DB::raw('SUM(pd.quantity) as quantity'),
                    DB::raw('SUM(pd.subtotal) as subtotal'),
                    DB::raw('SUM(p.tax_amount) as tax_amount'),
                    DB::raw('SUM(p.shipping_fee) as shipping_fee'),
                    DB::raw('SUM(p.total_amount) as total_amount'),
                    DB::raw('SUM(p.total_paid) as total_paid'),
                    DB::raw('SUM(p.balance) as balance')
                )
                ->join('purchases as p', 'p.purchase_id', '=', 'pd.purchase_id')
                ->join('users as u', 'u.id', '=', 'p.created_by')
                ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
                ->join('suppliers as sp', 'sp.supplier_id', '=', 'p.supplier_id')
                ->join('raw_materials as i', 'i.id', '=', 'pd.raw_material_id')
                ->where('p.is_deleted', 0)
                ->where('pf.id', $proId)
                ->groupBy(
                    'i.id',
                    'i.material_code',
                    'i.material_name',
                );
            if ($request->filled('supplier_id')) {
                $query->where('sp.supplier_id', $request->supplier_id);
            }

            if ($request->filled('created_by')) {
                $query->where('p.created_by', $request->created_by);
            }

            if ($request->filled('item_id')) {
                $query->where('i.id', $request->item_id);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('p.purchase_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('p.purchase_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('p.purchase_date', '<=', $request->end_date);
            }

            $results = $query->get();
        }else{

            $query = DB::table('purchase_details as pd')
                ->select(
                    'i.item_id',
                    'i.barcode',
                    'i.item_name',
                    DB::raw('SUM(pd.quantity) as quantity'),
                    'i.item_price',
                    'cg.category_name',
                    'br.brand_name',
                    DB::raw('SUM(pd.item_cost) as total_cost'),
                    DB::raw('SUM(pd.subtotal) as subtotal'),
                    DB::raw('SUM(p.tax_amount) as tax_amount'),
                    DB::raw('SUM(p.shipping_fee) as shipping_fee'),
                    DB::raw('SUM(p.total_amount) as total_amount'),
                    DB::raw('SUM(p.total_paid) as total_paid'),
                    DB::raw('SUM(p.balance) as balance')
                )
                ->join('purchases as p', 'p.purchase_id', '=', 'pd.purchase_id')
                ->join('users as u', 'u.id', '=', 'p.created_by')
                ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
                ->join('suppliers as sp', 'sp.supplier_id', '=', 'p.supplier_id')
                ->join('items as i', 'i.item_id', '=', 'pd.item_id')
                ->leftJoin('categories as cg', 'cg.category_id', '=', 'i.category_id')
                ->leftJoin('brands as br', 'br.brand_id', '=', 'i.brand_id')
                ->where('p.is_deleted', 0)
                ->where('pf.id', $proId)
                ->groupBy(
                    'i.item_id',
                    'i.barcode',
                    'i.item_name',
                    'i.item_price',
                    'cg.category_name',
                    'br.brand_name'
                );

            if ($request->filled('supplier_id')) {
                $query->where('sp.supplier_id', $request->supplier_id);
            }

            if ($request->filled('created_by')) {
                $query->where('p.created_by', $request->created_by);
            }

            if ($request->filled('item_id')) {
                $query->where('i.item_id', $request->item_id);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('p.purchase_date', [$request->start_date, $request->end_date]);
            } elseif ($request->filled('start_date')) {
                $query->where('p.purchase_date', '>=', $request->start_date);
            } elseif ($request->filled('end_date')) {
                $query->where('p.purchase_date', '<=', $request->end_date);
            }

            $results = $query->get();
        }

        return response()->json([
            'message' => 'purchase report by item get successfully',
            'status' => 200,
            'data' => $results
        ], 200);
    }

    public function purchaseReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'created_by' => 'nullable|integer|exists:users,id',
            'supplier_id' => 'nullable|integer'
        ]);

        $query = DB::table('purchases as p')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->leftJoin('suppliers as s', 's.supplier_id', '=', 'p.supplier_id')
            ->leftJoin('purchase_details as pd', function ($join) {
                $join->on('pd.purchase_id', '=', 'p.purchase_id')
                    ->where('pd.is_deleted', 0);
            })
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->select(
                'p.purchase_id',
                'p.purchase_no',
                's.supplier_name',
                's.supplier_tel',
                'p.purchase_date',
                'u.username as created_by',
                'p.shipping_fee',
                'p.tax_amount',
                'p.total_amount',
                'p.total_paid',
                'p.balance'
            )
            ->groupBy(
                'p.purchase_id',
                'p.purchase_no',
                's.supplier_name',
                's.supplier_tel',
                'p.purchase_date',
                'u.username',
                'p.shipping_fee',
                'p.tax_amount',
                'p.total_amount',
                'p.total_paid',
                'p.balance'
            );

        if (isset($validated['created_by'])) {
            $query->where('p.created_by', $validated['created_by']);
        }

        if (isset($validated['supplier_id'])) {
            $query->where('p.supplier_id', $validated['supplier_id']);
        }

        $startDate = $validated['start_date'] ?? '';
        $endDate = $validated['end_date'] ?? '';

        if ($startDate || $endDate) {
            $baseDateQuery = DB::table('purchases as p')
                ->join('users as u', 'u.id', '=', 'p.created_by')
                ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
                ->where('p.is_deleted', 0)
                ->where('pf.id', $proId);

            if (isset($validated['created_by'])) {
                $baseDateQuery->where('p.created_by', $validated['created_by']);
            }

            if (isset($validated['supplier_id'])) {
                $baseDateQuery->where('p.supplier_id', $validated['supplier_id']);
            }

            $startDate = $startDate ?: ($baseDateQuery->min('p.purchase_date') ?: date('Y-m-d'));
            $endDate = $endDate ?: ($baseDateQuery->max('p.purchase_date') ?: date('Y-m-d'));

            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    'message' => 'End date cannot be before start date.',
                    'status' => 422
                ], 422);
            }
            $query->whereBetween('purchase_date', [$startDate, $endDate]);
        }

        $purchases = $query->orderBy('p.purchase_date', 'desc')->get();

        return response()->json([
            'message' => 'Purchase report generated successfully!',
            'status' => 200,
            'data' => $purchases->map(function ($purchase) {
                return [
                    'purchase_no' => $purchase->purchase_no ?? 'N/A',
                    'supplier_name' => $purchase->supplier_name,
                    'supplier_tel' => $purchase->supplier_tel,
                    'purchase_date' => $purchase->purchase_date,
                    'created_by' => $purchase->created_by,
                    'shipping_fee' => number_format($purchase->shipping_fee, 2, '.', ''),
                    'tax_amount' => number_format($purchase->tax_amount, 2, '.', ''),
                    'total_amount' => number_format($purchase->total_amount, 2, '.', ''),
                    'total_paid' => number_format($purchase->total_paid, 2, '.', ''),
                    'balance' => number_format($purchase->balance, 2, '.', '')
                ];
            })
        ], 200);
    }

    public function productionReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'created_by' => 'nullable|integer|exists:users,id',
            'item_id' => 'nullable|integer|exists:items,item_id',
        ]);

        $query = DB::table('productions as p')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('items as i', 'i.item_id', '=', 'p.item_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->select(
                'p.id',
                'p.production_no',
                'p.production_date',
                'p.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_code',
                'p.quantity',
                'p.total_cost',
                'p.created_by',
                'u.username as created_by_name'
            );

        if (isset($validated['created_by'])) {
            $query->where('p.created_by', $validated['created_by']);
        }

        if (isset($validated['item_id'])) {
            $query->where('p.item_id', $validated['item_id']);
        }

        $startDate = $validated['start_date'] ?? '';
        $endDate = $validated['end_date'] ?? '';

        if ($startDate || $endDate) {
            $startDate = $startDate ?: Production::where('is_deleted', 0)->min('production_date') ?: date('Y-m-d');
            $endDate = $endDate ?: Production::where('is_deleted', 0)->max('production_date') ?: date('Y-m-d');

            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    'message' => 'End date cannot be before start date.',
                    'status' => 422
                ], 422);
            }

            $query->whereBetween('p.production_date', [$startDate, $endDate]);
        }

        $productions = $query->orderByDesc('p.id')->get();
        $productionIds = $productions->pluck('id');

        $detailRows = DB::table('production_details as pd')
            ->leftJoin('raw_materials as rm', 'rm.id', '=', 'pd.raw_material_id')
            ->whereIn('pd.production_id', $productionIds)
            ->where('pd.is_deleted', 0)
            ->select(
                'pd.production_id',
                'pd.raw_material_id',
                'rm.material_name as raw_material_name',
                'rm.material_code as raw_material_code',
                'pd.quantity',
                'pd.cost_per_unit',
                'pd.total_cost'
            )
            ->get()
            ->groupBy('production_id');

        return response()->json([
            'message' => 'Production report generated successfully!',
            'status' => 200,
            'data' => $productions->map(function ($production) use ($detailRows) {
                $details = $detailRows->get($production->id, collect());

                return [
                    'production_no' => $production->production_no,
                    'production_date' => $production->production_date,
                    'barcode' => $production->barcode,
                    'item_name' => $production->item_name,
                    'item_code' => $production->item_code,
                    'quantity' => (float) $production->quantity,
                    'total_cost' => number_format($production->total_cost, 2, '.', ''),
                    'created_by' => $production->created_by_name,
                    'details' => $details->map(function ($detail) {
                        return [
                            'raw_material_id' => $detail->raw_material_id,
                            'raw_material_name' => $detail->raw_material_name,
                            'raw_material_code' => $detail->raw_material_code,
                            'quantity' => (float) $detail->quantity,
                            'cost_per_unit' => number_format($detail->cost_per_unit, 2, '.', ''),
                            'total_cost' => number_format($detail->total_cost, 2, '.', ''),
                        ];
                    })->values(),
                ];
            })
        ], 200);
    }


    public function productionReportByItem(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'created_by' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DB::table('productions as p')
            ->select(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_code',
                DB::raw('SUM(p.quantity) as quantity'),
                DB::raw('SUM(p.total_cost) as total_cost'),
                DB::raw('CASE WHEN SUM(p.quantity) > 0 THEN SUM(p.total_cost) / SUM(p.quantity) ELSE 0 END as cost_per_unit')
            )
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('items as i', 'i.item_id', '=', 'p.item_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->groupBy(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_code'
            );

        if ($request->filled('created_by')) {
            $query->where('p.created_by', $request->created_by);
        }

        if ($request->filled('item_id')) {
            $query->where('i.item_id', $request->item_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('p.production_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('p.production_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('p.production_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'production report by item get successfully',
            'status' => 200,
            'data' => $results->map(function ($result) {
                return [
                    'item_id' => $result->item_id,
                    'barcode' => $result->barcode,
                    'item_name' => $result->item_name,
                    'item_code' => $result->item_code,
                    'quantity' => number_format($result->quantity, 2, '.', ''),
                    'cost_per_unit' => number_format($result->cost_per_unit, 2, '.', ''),
                    'total_cost' => number_format($result->total_cost, 2, '.', ''),
                ];
            })
        ], 200);
    }

    public function productionReportByRaw(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'created_by' => 'nullable|integer',
            'raw_material_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DB::table('production_details as pd')
            ->select(
                'rm.id',
                'rm.material_code',
                'rm.material_name',
                'rm.primary_unit',
                'rm.secondary_unit',
                'rm.conversion_value',
                DB::raw('SUM(pd.quantity) as quantity'),
                DB::raw('AVG(pd.cost_per_unit) as cost_per_unit'),
                DB::raw('SUM(pd.total_cost) as total_cost'),
                DB::raw('SUM(p.quantity) as production_quantity'),
                DB::raw('SUM(p.total_cost) as production_total_cost')
            )
            ->join('productions as p', 'p.id', '=', 'pd.production_id')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('raw_materials as rm', 'rm.id', '=', 'pd.raw_material_id')
            ->where('p.is_deleted', 0)
            ->where('pd.is_deleted', 0)
            ->where('pf.id', $proId)
            ->groupBy(
                'rm.id',
                'rm.material_code',
                'rm.material_name',
                'rm.primary_unit',
                'rm.secondary_unit',
                'rm.conversion_value'
            );

        if ($request->filled('created_by')) {
            $query->where('p.created_by', $request->created_by);
        }

        if ($request->filled('raw_material_id')) {
            $query->where('rm.id', $request->raw_material_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('p.production_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('p.production_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('p.production_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'production report by raw get successfully',
            'status' => 200,
            'data' => $results->map(function ($result) {
                return [
                    'id' => $result->id,
                    'material_code' => $result->material_code,
                    'material_name' => $result->material_name,
                    'primary_unit' => $result->primary_unit,
                    'secondary_unit' => $result->secondary_unit,
                    'conversion_value' => number_format($result->conversion_value, 2, '.', ''),
                    'quantity' => number_format($result->quantity, 2, '.', ''),
                    'cost_per_unit' => number_format($result->cost_per_unit, 2, '.', ''),
                    'total_cost' => number_format($result->total_cost, 2, '.', ''),
                    'production_quantity' => number_format($result->production_quantity, 2, '.', ''),
                    'production_total_cost' => number_format($result->production_total_cost, 2, '.', '')
                ];
            })
        ], 200);
    }

    public function stockReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'created_by' => 'nullable|integer|exists:users,id',
            'stock_type_id' => 'nullable|integer|exists:stock_types,stock_type_id',
        ]);

        $query = DB::table('stock_masters as sm')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->leftJoin('stock_types as st', 'st.stock_type_id', '=', 'sm.stock_type_id')
            ->leftJoin('warehouses as from_w', 'from_w.warehouse_id', '=', 'sm.from_warehouse')
            ->leftJoin('warehouses as to_w', 'to_w.warehouse_id', '=', 'sm.warehouse_id')
            ->where('sm.is_deleted', 0)
            ->where('pf.id', $proId)
            ->select(
                'sm.stock_id',
                'sm.stock_no',
                'sm.stock_date',
                'sm.stock_remark',
                'sm.stock_type_id',
                'st.stock_type_name',
                'sm.from_warehouse',
                'from_w.warehouse_name as from_warehouse_name',
                'sm.warehouse_id',
                'to_w.warehouse_name as to_warehouse_name',
                'sm.quantity',
                // 'sm.exchange_rate',
                'sm.stock_created_by',
                'u.username as created_by_name'
            );

        if (isset($validated['created_by'])) {
            $query->where('sm.stock_created_by', $validated['created_by']);
        }

        if (isset($validated['stock_type_id'])) {
            $query->where('sm.stock_type_id', $validated['stock_type_id']);
        }

        $startDate = $validated['start_date'] ?? '';
        $endDate = $validated['end_date'] ?? '';

        if ($startDate || $endDate) {
            $startDate = $startDate ?: StockMaster::where('is_deleted', 0)->min('stock_date') ?: date('Y-m-d');
            $endDate = $endDate ?: StockMaster::where('is_deleted', 0)->max('stock_date') ?: date('Y-m-d');

            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    'message' => 'End date cannot be before start date.',
                    'status' => 422
                ], 422);
            }

            $query->whereBetween('sm.stock_date', [$startDate, $endDate]);
        }

        $stocks = $query->orderByDesc('sm.stock_id')->get();


        return response()->json([
            'message' => 'Stock report generated successfully!',
            'status' => 200,
            'data' => $stocks,
        ], 200);
    }
    public function stockReportByItem(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'created_by' => 'nullable|integer|exists:users,id',
            'stock_type_id' => 'nullable|integer|exists:stock_types,stock_type_id',
            'item_id' => 'nullable|integer|exists:items,item_id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DB::table('stock_details as sd')
            ->select(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_code',
                'cg.category_name',
                'br.brand_name',
                // DB::raw('SUM(sd.quantity) as quantity'),
                // DB::raw('AVG(sd.item_cost) as item_cost'),
                // DB::raw('SUM(sd.quantity * sd.item_cost) as subtotal'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_sale')
            )
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'sd.stock_id')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('items as i', 'i.item_id', '=', 'sd.item_id')
            ->leftJoin('categories as cg', 'cg.category_id', '=', 'i.category_id')
            ->leftJoin('brands as br', 'br.brand_id', '=', 'i.brand_id')
            ->where('sm.is_deleted', 0)
            ->where('sd.is_deleted', 0)
            ->where('pf.id', $proId)
            ->groupBy(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'i.item_code',
                'cg.category_name',
                'br.brand_name'
            );

        if ($request->filled('created_by')) {
            $query->where('sm.stock_created_by', $request->created_by);
        }

        if ($request->filled('stock_type_id')) {
            $query->where('sm.stock_type_id', $request->stock_type_id);
        }

        if ($request->filled('item_id')) {
            $query->where('i.item_id', $request->item_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sm.stock_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('sm.stock_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('sm.stock_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'stock report by item get successfully',
            'status' => 200,
            'data' => $results
        ], 200);
    }


    public function RawMaterialReport(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'created_by' => 'nullable|integer|exists:users,id',
            'stock_type_id' => 'nullable|integer|exists:stock_types,stock_type_id',
            'raw_material_id' => 'nullable|integer|exists:raw_materials,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DB::table('stock_raw_details as sd')
            ->select(
                DB::raw('SUM(sd.quantity) as quantity'),
                DB::raw('AVG(sd.item_cost) as avg_item_cost'),
                DB::raw('SUM(sd.quantity * sd.item_cost) as subtotal_cost'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_return_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_in_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_out_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_waste'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_used'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_used'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_used_kh')
            )
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'sd.stock_id')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('raw_materials as rm', 'rm.id', '=', 'sd.raw_material_id')
            ->where('sm.is_deleted', 0)
            ->where('sd.is_deleted', 0)
            ->where('pf.id', $proId);

        if ($request->filled('created_by')) {
            $query->where('sm.stock_created_by', $request->created_by);
        }

        if ($request->filled('stock_type_id')) {
            $query->where('sm.stock_type_id', $request->stock_type_id);
        }

        if ($request->filled('raw_material_id')) {
            $query->where('rm.id', $request->raw_material_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sm.stock_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('sm.stock_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('sm.stock_date', '<=', $request->end_date);
        }

        $results = $query->first();

        $queryProduction = DB::table('production_details as pdd')
            ->join('productions as pd','pdd.production_id','=','pd.id')
            ->join('users as u', 'u.id', '=', 'pd.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->select(
                DB::raw('SUM(pdd.total_cost) as cost_used'),
                DB::raw('SUM(pdd.total_cost * pd.exchange_rate) as cost_used_kh'),
                DB::raw('SUM(pdd.quantity) as stock_used')
            )
            ->where('pd.is_deleted', 0)
            ->where('pdd.is_deleted', 0)
            ->where('pf.id', $proId);
        if ($request->filled('created_by')) {
            $queryProduction->where('pd.created_by', $request->created_by);
        }

        if ($request->filled('raw_material_id')) {
            $queryProduction->where('pdd.raw_material_id', $request->raw_material_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $queryProduction->whereBetween('pd.production_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $queryProduction->where('pd.production_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $queryProduction->where('pd.production_date', '<=', $request->end_date);
        }

        $resultProdution = $queryProduction->first();

        $results->stock_used = $resultProdution->stock_used;
        $results->cost_used = $resultProdution->cost_used;
        $results->cost_used_kh = $resultProdution->cost_used_kh;
        $data = [
            "quantity"=> $results->quantity ?? 0,
            "avg_item_cost"=> $results->avg_item_cost ?? 0,
            "subtotal_cost"=> $results->subtotal_cost ?? 0,
            "stock_return"=> $results->stock_return ?? 0,
            "cost_return"=> $results->cost_return ?? 0,
            "cost_return_kh"=> $results->cost_return_kh ?? 0,
            "stock_in"=> $results->stock_in ?? 0,
            "cost_in"=> $results->cost_in ?? 0,
            "cost_in_kh"=> $results->cost_in_kh ?? 0,
            "stock_out"=> $results->stock_out ?? 0,
            "cost_out"=> $results->cost_out ?? 0,
            "cost_out_kh"=> $results->cost_out_kh ?? 0,
            "stock_waste"=> $results->stock_waste ?? 0,
            "cost_waste"=> $results->cost_waste ?? 0,
            "cost_waste_kh"=> $results->cost_waste_kh ?? 0,
            "stock_used"=> $results->stock_used ?? 0,
            "cost_used"=> $results->cost_used ?? 0,
            "cost_used_kh"=> $results->cost_used_kh ?? 0,
            "stock_out"=> $results->stock_out ?? 0,
            "calculate_cost"=> ($results->cost_return ?? 0) + ($results->cost_in ?? 0) - ($results->cost_out ?? 0) - ($results->cost_waste ?? 0) - ($results->cost_used ?? 0),
            "calculate_cost_kh"=> ($results->cost_return_kh ?? 0) + ($results->cost_in_kh ?? 0) - ($results->cost_out_kh ?? 0) - ($results->cost_waste_kh ?? 0) - ($results->cost_used_kh ?? 0)
        ];


        return response()->json([
            'message' => 'stock report by item get successfully',
            'status' => 200,
            'data' => $data
        ], 200);
    }

    public function AnalysisProfit(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $exchange_rate = ExchangeRate::find($proId);


        $query = DB::table('stock_details as sd')
            ->select(
                DB::raw('SUM(sd.quantity) as quantity'),
                DB::raw('AVG(sd.item_cost) as avg_item_cost'),
                DB::raw('SUM(sd.quantity * sd.item_cost) as subtotal_cost'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_return_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_in_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_out_kh'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_waste'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_waste'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS stock_used'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity * sd.item_cost ELSE 0 END) AS cost_used'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN (sd.quantity * sd.item_cost) * sm.exchange_rate ELSE 0 END) AS cost_used_kh')
            )
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'sd.stock_id')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('items as i', 'i.item_id', '=', 'sd.item_id')
            ->leftJoin('categories as cg', 'cg.category_id', '=', 'i.category_id')
            ->leftJoin('brands as br', 'br.brand_id', '=', 'i.brand_id')
            ->where('sm.is_deleted', 0)
            ->where('i.item_type', 1)
            ->where('sd.is_deleted', 0)
            ->where('pf.id', $proId);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('sm.stock_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('sm.stock_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('sm.stock_date', '<=', $request->end_date);
        }

        $results = $query->first();

        $queryProduction = DB::table('production_details as pdd')
            ->join('productions as pd','pdd.production_id','=','pd.id')
            ->join('users as u', 'u.id', '=', 'pd.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->select(
                DB::raw('SUM(pdd.total_cost) as cost_used'),
                DB::raw('SUM(pdd.total_cost * pd.exchange_rate) as cost_used_kh'),
                DB::raw('SUM(pdd.quantity) as stock_used')
            )
            ->where('pd.is_deleted', 0)
            ->where('pdd.is_deleted', 0)
            ->where('pf.id', $proId);


        if ($request->filled('start_date') && $request->filled('end_date')) {
            $queryProduction->whereBetween('pd.production_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $queryProduction->where('pd.production_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $queryProduction->where('pd.production_date', '<=', $request->end_date);
        }

        $resultProdution = $queryProduction->first();


        $queryOrder = DB::table('order_items as oi')
            ->select(
                DB::raw('SUM(om.order_total) as order_total'),
                DB::raw('SUM(om.order_total * om.exchange_rate) as order_total_kh')
            )
            ->join('order_masters as om', 'om.order_id', '=', 'oi.order_id')
            ->join('customers as c','c.customer_id','=','om.order_customer_id')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId);

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $queryOrder->whereBetween('om.order_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $queryOrder->where('om.order_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $queryOrder->where('om.order_date', '<=', $request->end_date);
        }

        $resultOrder = $queryOrder->first();


        $queryExpense = DB::table('expense_masters as em')
            ->select(
                DB::raw('SUM(em.amount) as total_amount'),
            )
            ->join('users as u', 'u.id', '=', 'em.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId);

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $queryExpense->whereBetween('em.expense_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $queryExpense->where('em.expense_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $queryExpense->where('em.expense_date', '<=', $request->end_date);
        }

        $resultExpense = $queryExpense->first();

        $results->stock_used = $resultProdution->stock_used;
        $results->cost_used = $resultProdution->cost_used;
        $results->cost_used_kh = $resultProdution->cost_used_kh;
        $cost = ($results->cost_in ?? 0) + ($results->cost_used ?? 0) + ($resultExpense->total_amount ?? 0);
        $cost_kh = ($results->cost_in_kh ?? 0) + ($results->cost_used_kh ?? 0) + ($resultExpense->total_amount * $exchange_rate->usd_to_khr??0);
        $data = [
            "cost_return"=> $results->cost_return ?? 0,
            "cost_return_kh"=> $results->cost_return_kh ?? 0,
            "cost_in"=> $results->cost_in ?? 0,
            "cost_in_kh"=> $results->cost_in_kh ?? 0,
            "total_expense_cost"=> $resultExpense->total_amount ?? 0,
            "total_expense_cost_kh"=> $resultExpense->total_amount * $exchange_rate->usd_to_khr?? 0,
            "order_amount"=> $resultOrder->order_total ?? 0,
            "order_amount_kh"=> $resultOrder->order_total_kh ?? 0,
            "cost_used"=> $results->cost_used ?? 0,
            "cost_used_kh"=> $results->cost_used_kh ?? 0,
            "total_cost"=> $cost,
            "total_cost_kh"=> $cost_kh,
            "profit"=> ((int)$resultOrder->order_total ?? 0) - (int)$cost,
            "profit_kh"=> ((int)$resultOrder->order_total_kh ?? 0) - (int)$cost_kh
        ];



        return response()->json([
            'message' => 'stock report by item get successfully',
            'status' => 200,
            'data' => $data
        ], 200);
    }


    public function AnalysisProfitChart(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);
        $startDate = $request->start_date ?: date('Y-01-01');
        $endDate = $request->end_date ?: date('Y-12-31');

        if (strtotime($endDate) < strtotime($startDate)) {
            return response()->json([
                'message' => 'End date cannot be before start date.',
                'status' => 422
            ], 422);
        }

        $orderByMonth = DB::table('order_masters as om')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->whereBetween('om.order_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(om.order_date, '%Y-%m')"))
            ->select(
                DB::raw("DATE_FORMAT(om.order_date, '%Y-%m') as ym"),
                DB::raw('SUM(om.order_total) as revenue')
            )
            ->pluck('revenue', 'ym');

        $stockCostByMonth = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'sd.stock_id')
            ->join('users as u', 'u.id', '=', 'sm.stock_created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->join('items as i', 'i.item_id', '=', 'sd.item_id')
            ->where('sm.is_deleted', 0)
            ->where('sd.is_deleted', 0)
            ->where('i.item_type', 1)
            ->where('pf.id', $proId)
            ->whereBetween('sm.stock_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(sm.stock_date, '%Y-%m')"))
            ->select(
                DB::raw("DATE_FORMAT(sm.stock_date, '%Y-%m') as ym"),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity * sd.item_cost ELSE 0 END) as cost_in')
            )
            ->pluck('cost_in', 'ym');

        $productionCostByMonth = DB::table('production_details as pdd')
            ->join('productions as pd', 'pdd.production_id', '=', 'pd.id')
            ->join('users as u', 'u.id', '=', 'pd.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->where('pd.is_deleted', 0)
            ->where('pdd.is_deleted', 0)
            ->where('pf.id', $proId)
            ->whereBetween('pd.production_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(pd.production_date, '%Y-%m')"))
            ->select(
                DB::raw("DATE_FORMAT(pd.production_date, '%Y-%m') as ym"),
                DB::raw('SUM(pdd.total_cost) as cost_used')
            )
            ->pluck('cost_used', 'ym');

        $expenseByMonth = DB::table('expense_masters as em')
            ->join('users as u', 'u.id', '=', 'em.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->whereBetween('em.expense_date', [$startDate, $endDate])
            ->groupBy(DB::raw("DATE_FORMAT(em.expense_date, '%Y-%m')"))
            ->select(
                DB::raw("DATE_FORMAT(em.expense_date, '%Y-%m') as ym"),
                DB::raw('SUM(em.amount) as expense')
            )
            ->pluck('expense', 'ym');

        $data = [];
        $cursor = strtotime(date('Y-m-01', strtotime($startDate)));
        $last = strtotime(date('Y-m-01', strtotime($endDate)));

        while ($cursor <= $last) {
            $ym = date('Y-m', $cursor);
            $month = $ym . '-01';
            $revenue = (float) ($orderByMonth[$ym] ?? 0);
            $cost = (float) ($stockCostByMonth[$ym] ?? 0)
                + (float) ($productionCostByMonth[$ym] ?? 0)
                + (float) ($expenseByMonth[$ym] ?? 0);

            $data[] = [
                'month' => $month,
                'profit' => number_format($revenue - $cost, 2),
                'revenue' => number_format($revenue, 2),
                'cost' => $cost
            ];

            $cursor = strtotime('+1 month', $cursor);
        }

        return response()->json([
            'message' => 'profit analysis chart get successfully',
            'status' => 200,
            'data' => $data
        ], 200);
    }


    public function reportAP(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'supplier_id' => 'nullable|integer|exists:suppliers,supplier_id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $exchangeRate = ExchangeRate::find($proId);

        $query = DB::table('purchases as p')
            ->select(
                'p.purchase_no',
                'p.purchase_date',
                'p.total_amount',
                'p.total_paid',
                'p.balance',
                'p.exchange_rate',
                'sp.supplier_name'
            )
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->leftJoin('suppliers as sp', 'sp.supplier_id', '=', 'p.supplier_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId);

        if ($request->filled('user_id')) {
            $query->where('p.created_by', $request->user_id);
        }

        if ($request->filled('supplier_id')) {
            $query->where('p.supplier_id', $request->supplier_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('p.purchase_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('p.purchase_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('p.purchase_date', '<=', $request->end_date);
        }

        $results = $query->get();

        $totals = [
            'total' => 0,
            'total_paid' => 0,
            'total_balance' => 0,
            'total_kh' => 0,
            'total_paid_kh' => 0,
            'total_balance_kh' => 0,
        ];

        $summary = $results->map(function ($row) use (&$totals, $exchangeRate) {
            $rate = $row->exchange_rate ?? ($exchangeRate->usd_to_khr ?? 0);
            $total = (float) ($row->total_amount ?? 0);
            $paid = (float) ($row->total_paid ?? 0);
            $balance = (float) ($row->balance ?? 0);

            $totalKh = $total * $rate;
            $paidKh = $paid * $rate;
            $balanceKh = $balance * $rate;

            $totals['total'] += $total;
            $totals['total_paid'] += $paid;
            $totals['total_balance'] += $balance;
            $totals['total_kh'] += $totalKh;
            $totals['total_paid_kh'] += $paidKh;
            $totals['total_balance_kh'] += $balanceKh;

            return [
                'purchase_no' => $row->purchase_no,
                'supplier_name' => $row->supplier_name,
                'purchase_date' => $row->purchase_date,
                'total' => number_format($total, 2, '.', ''),
                'paid' => number_format($paid, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
                'total_kh' => number_format($totalKh, 2, '.', ''),
                'paid_kh' => number_format($paidKh, 2, '.', ''),
                'balance_kh' => number_format($balanceKh, 2, '.', ''),
            ];
        });

        return response()->json([
            'message' => 'Account Payables Report',
            'status' => 200,
            'data' => [
                'summary' => $summary,
                'total' => number_format($totals['total'], 2, '.', ''),
                'total_paid' => number_format($totals['total_paid'], 2, '.', ''),
                'total_balance' => number_format($totals['total_balance'], 2, '.', ''),
                'total_kh' => number_format($totals['total_kh'], 2, '.', ''),
                'total_paid_kh' => number_format($totals['total_paid_kh'], 2, '.', ''),
                'total_balance_kh' => number_format($totals['total_balance_kh'], 2, '.', ''),
            ]
        ], 200);
    }


    public function reportAR(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'customer_id' => 'nullable|integer|exists:customers,customer_id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = DB::table('order_masters as om')
            ->select(
                'om.order_no',
                'om.order_date',
                'om.order_total',
                'om.payment',
                'om.balance',
                'om.exchange_rate',
                'c.customer_name',
                'c.customer_tel',
                'u.username as created_by_name'
            )
            ->join('customers as c', 'c.customer_id', '=', 'om.order_customer_id')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('om.is_deleted', 0)
            ->where('p.id', $proId);

        if ($request->filled('user_id')) {
            $query->where('om.created_by', $request->user_id);
        }

        if ($request->filled('customer_id')) {
            $query->where('c.customer_id', $request->customer_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('om.order_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('om.order_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('om.order_date', '<=', $request->end_date);
        }

        $results = $query->get();

        $totals = [
            'total' => 0,
            'total_paid' => 0,
            'total_balance' => 0,
            'total_kh' => 0,
            'total_paid_kh' => 0,
            'total_balance_kh' => 0,
        ];

        $summary = $results->map(function ($row) use (&$totals) {
            $rate = $row->exchange_rate ?? 0;
            $total = (float) ($row->order_total ?? 0);
            $paid = (float) ($row->payment ?? 0);
            $balance = (float) ($row->balance ?? 0);

            $totalKh = $total * $rate;
            $paidKh = $paid * $rate;
            $balanceKh = $balance * $rate;

            $totals['total'] += $total;
            $totals['total_paid'] += $paid;
            $totals['total_balance'] += $balance;
            $totals['total_kh'] += $totalKh;
            $totals['total_paid_kh'] += $paidKh;
            $totals['total_balance_kh'] += $balanceKh;

            return [
                'order_no' => $row->order_no,
                'customer_name' => $row->customer_name,
                'customer_tel' => $row->customer_tel,
                'order_date' => $row->order_date,
                'created_by_name' => $row->created_by_name,
                'total' => number_format($total, 2, '.', ''),
                'paid' => number_format($paid, 2, '.', ''),
                'balance' => number_format($balance, 2, '.', ''),
                'total_kh' => number_format($totalKh, 2, '.', ''),
                'paid_kh' => number_format($paidKh, 2, '.', ''),
                'balance_kh' => number_format($balanceKh, 2, '.', ''),
            ];
        });

        return response()->json([
            'message' => 'Account Receivables Report',
            'status' => 200,
            'data' => [
                'summary' => $summary,
                'total' => number_format($totals['total'], 2, '.', ''),
                'total_paid' => number_format($totals['total_paid'], 2, '.', ''),
                'total_balance' => number_format($totals['total_balance'], 2, '.', ''),
                'total_kh' => number_format($totals['total_kh'], 2, '.', ''),
                'total_paid_kh' => number_format($totals['total_paid_kh'], 2, '.', ''),
                'total_balance_kh' => number_format($totals['total_balance_kh'], 2, '.', ''),
            ]
        ], 200);
    }


    public function debtAnalysis(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->start_date ?: date('Y-m-01');
        $endDate = $request->end_date ?: date('Y-m-d');

        if (strtotime($endDate) < strtotime($startDate)) {
            return response()->json([
                'message' => 'End date cannot be before start date.',
                'status' => 422
            ], 422);
        }

        $arTotals = DB::table('order_masters as om')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('om.is_deleted', 0)
            ->where('p.id', $proId)
            ->whereBetween('om.order_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(om.balance) as total_balance'),
                DB::raw('SUM(om.balance * IFNULL(om.exchange_rate, 0)) as total_balance_kh')
            )
            ->first();

        $apTotals = DB::table('purchases as p')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->where('p.purchase_type', 0)
            ->whereBetween('p.purchase_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(p.balance) as total_balance'),
                DB::raw('SUM(p.balance * IFNULL(p.exchange_rate, 0)) as total_balance_kh')
            )
            ->first();

        $invTotals = DB::table('purchases as p')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->where('p.purchase_type', 1)
            ->whereBetween('p.purchase_date', [$startDate, $endDate])
            ->select(
                DB::raw('SUM(p.balance) as total_balance'),
                DB::raw('SUM(p.balance * IFNULL(p.exchange_rate, 0)) as total_balance_kh')
            )
            ->first();

        $arTotal = (float) ($arTotals->total_balance ?? 0);
        $arTotalKh = (float) ($arTotals->total_balance_kh ?? 0);
        $apTotal = (float) ($apTotals->total_balance ?? 0);
        $apTotalKh = (float) ($apTotals->total_balance_kh ?? 0);
        $invTotal = (float) ($invTotals->total_balance ?? 0);
        $invTotalKh = (float) ($invTotals->total_balance_kh ?? 0);

        $balanceTotal = $arTotal - ($apTotal + $invTotal);
        $balanceTotalKh = $arTotalKh - ($apTotalKh + $invTotalKh);

        $arByDate = DB::table('order_masters as om')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('om.is_deleted', 0)
            ->where('p.id', $proId)
            ->whereBetween('om.order_date', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(om.order_date)'))
            ->select(
                DB::raw('DATE(om.order_date) as date'),
                DB::raw('SUM(om.balance) as total_balance')
            )
            ->pluck('total_balance', 'date');

        $apInvByDate = DB::table('purchases as p')
            ->join('users as u', 'u.id', '=', 'p.created_by')
            ->join('profiles as pf', 'pf.id', '=', 'u.profile_id')
            ->where('p.is_deleted', 0)
            ->where('pf.id', $proId)
            ->whereIn('p.purchase_type', [0, 1])
            ->whereBetween('p.purchase_date', [$startDate, $endDate])
            ->groupBy(DB::raw('DATE(p.purchase_date)'))
            ->select(
                DB::raw('DATE(p.purchase_date) as date'),
                DB::raw('SUM(p.balance) as total_balance')
            )
            ->pluck('total_balance', 'date');

        $chart = [];
        $cursor = strtotime($startDate);
        $last = strtotime($endDate);
        while ($cursor <= $last) {
            $date = date('Y-m-d', $cursor);
            $arDay = (float) ($arByDate[$date] ?? 0);
            $apInvDay = (float) ($apInvByDate[$date] ?? 0);
            $chart[] = [
                'date' => $date,
                'ar' => (float)number_format($arDay,2,'.') ,
                'ap_inv' =>(float)number_format($arDay - $apInvDay,2,'.'),
            ];
            $cursor = strtotime('+1 day', $cursor);
        }

        return response()->json([
            'message' => 'Debt analysis generated successfully',
            'status' => 200,
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'cards' => [
                    'ar_total' => number_format($arTotal, 2, '.', ''),
                    'ar_total_kh' => number_format($arTotalKh, 2, '.', ''),
                    'ap_total' => number_format($apTotal, 2, '.', ''),
                    'ap_total_kh' => number_format($apTotalKh, 2, '.', ''),
                    'inv_total' => number_format($invTotal, 2, '.', ''),
                    'inv_total_kh' => number_format($invTotalKh, 2, '.', ''),
                    'balance_total' => number_format($balanceTotal, 2, '.', ''),
                    'balance_total_kh' => number_format($balanceTotalKh, 2, '.', '')
                ],
                'chart' => $chart
            ]
        ], 200);
    }


    public function incomeStatement(Request $request){

        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $user = Auth::user();
        $proId = $user->profile_id;

        $revenueQuery = DB::table('order_masters as om')
            ->join('users as u', 'u.id','=','om.created_by')
            ->join('profiles as p', 'p.id', '=', 'u.profile_id')
            ->where('p.id', $proId)
            ->where('om.is_deleted', 0)
            ->when($start_date, function ($revenueQuery) use ($start_date) {
                    $revenueQuery->whereDate('om.order_date', '>=', $start_date);
            })
            ->when($end_date, function ($revenueQuery) use ($end_date) {
                $revenueQuery->whereDate('om.order_date', '<=', $end_date);
            })->select(
                DB::raw('SUM(om.order_total) as revenue')
            )->get();

        $quantityRevenue = DB::table('order_items as oi')
            ->join('order_masters as om', 'om.order_id', '=', 'oi.order_id')
            ->join('users as u', 'u.id','=','om.created_by')
            ->join('profiles as p', 'p.id', '=', 'u.profile_id')
            ->where('p.id', $proId)
            ->where('om.is_deleted', 0)
            ->when($start_date, function ($revenueQuery) use ($start_date) {
                    $revenueQuery->whereDate('om.order_date', '>=', $start_date);
            })
            ->when($end_date, function ($revenueQuery) use ($end_date) {
                $revenueQuery->whereDate('om.order_date', '<=', $end_date);
            })->select(
                'oi.item_id',
                DB::raw('SUM(oi.quantity) as quantity')
            )
            ->groupBy('oi.item_id')
            ->get();


        $stockWaste = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sm.stock_id', '=', 'sd.stock_id')
            ->join('users as u', 'u.id','=','sm.stock_created_by')
            ->join('profiles as p', 'p.id', '=', 'u.profile_id')
            ->where('p.id', $proId)
            ->where('sm.stock_type_id', 4)
            ->when($start_date, function ($stockWaste) use ($start_date) {
                    $stockWaste->whereDate('sm.stock_date', '>=', $start_date);
            })
            ->when($end_date, function ($stockWaste) use ($end_date) {
                $stockWaste->whereDate('sm.stock_date', '<=', $end_date);
            })->select(
                'sd.item_id',
                DB::raw('SUM(sd.quantity) as quantity')
            )
            ->groupBy('sd.item_id')
            ->get();


        $expense = DB::table('expense_masters as em')
            ->join('users as u', 'u.id','=','em.created_by')
            ->join('profiles as p', 'p.id', '=', 'u.profile_id')
            ->where('p.id', $proId)
            ->when($start_date, function ($stockWaste) use ($start_date) {
                    $stockWaste->whereDate('em.expense_date', '>=', $start_date);
            })
            ->when($end_date, function ($stockWaste) use ($end_date) {
                $stockWaste->whereDate('em.expense_date', '<=', $end_date);
            })->select(
                DB::raw('SUM(em.amount) as amount')
            )
            ->get();

        $cost = 0;
        $waste = 0;
        $rest_cost=0;
        foreach($quantityRevenue as $item){
            $cost += $this->detailService->calculateTotalCost('purchase_details', 'item_id', $item->item_id, $item->quantity)['totalCost'];
            $rest_cost += $this->detailService->calculateTotalCost('purchase_details', 'item_id', $item->item_id, $item->quantity)['restCost'];
        }
        foreach($stockWaste as $item){
            $waste += $this->detailService->calculateTotalCost('purchase_details', 'item_id', $item->item_id, $item->quantity)['totalCost'];
        }


        $revenue = (float)number_format($revenueQuery[0]->revenue, 2, '.','');
        $total_cost = (float)number_format($cost-$waste, 2, '.','');
        $cross_profit = (float)number_format($revenue-$total_cost, 2, '.','');
        $expense_cost = (float)number_format($expense[0]->amount, 2, '.','');
        $net_profit = (float)number_format($cross_profit-$expense_cost, 2, '.','');

        return response()->json([
            'message'=> 'Income statement get successfully',
            'status'=>200,
            'data'=>[
                'revenue'=> $revenue,
            // 'revenue_quantity'=> $quantityRevenue,
            'total_cogs'=> (float)number_format($cost, 2, '.',''),
            'total_wc' => (float)number_format($waste, 2, '.',''),
            'total_cost'=> $total_cost,
            'cross_profit'=> $cross_profit,
            'total_rsc'=> (float)number_format($rest_cost, 2, '.',''),
            'expense_cost'=> $expense_cost,
            'net_profit'=>$net_profit
            ]
        ],200);
    }
}



