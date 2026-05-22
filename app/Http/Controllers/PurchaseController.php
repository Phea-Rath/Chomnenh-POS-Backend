<?php

namespace App\Http\Controllers;

use App\Models\Items;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\PurchaseRawDetail;
use App\Models\Purchases;
use App\Models\RawMaterial;
use App\Models\PurchaseDetails;
use App\Models\PurchasePayment;
use App\Models\PurchasePayments;
use App\Models\PurchaseAttribute;
use App\Models\StockDetails;
use App\Models\StockRawDetail;
use App\Models\StockAttribute;
use App\Models\Suppliers;
use App\Services\DetailService;
use App\Models\ExchangeRate;
use App\Models\StockMaster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class PurchaseController extends Controller
{
    protected $detailService;

    public function __construct(DetailService $detailService)
    {
        $this->detailService = $detailService;
    }

    public function indexMobile(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = DB::table('purchases as p')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->where('p.is_deleted', 0)
            ->where('pr.id', $proId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.purchase_no', 'LIKE', "%{$search}%")
                    ->orWhere('s.supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('u.username', 'LIKE', "%{$search}%");
            });
        }

        $rawPurchases = $query->select('p.purchase_id')
            ->orderBy('p.purchase_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawPurchases->total() === 0) {
            return response()->json([
                'message' => 'Purchase get successfully',
                'status' => 200,
                'data' => [],
            ]);
        }

        $purchases = [];
        foreach ($rawPurchases as $row) {
            $formatted = $this->formatMobilePurchase((int) $row->purchase_id, $proId);
            if ($formatted) {
                $purchases[] = $formatted;
            }
        }

        return response()->json([
            'message' => 'Purchase get successfully',
            'status' => 200,
            'data' => $purchases,
            'pagination' => [
                'current_page' => $rawPurchases->currentPage(),
                'per_page' => $rawPurchases->perPage(),
                'total' => $rawPurchases->total(),
                'last_page' => $rawPurchases->lastPage(),
            ],
        ]);
    }
    public function showMobile($id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $data = $this->formatMobilePurchase((int) $id, $proId);
        if (!$data) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status' => 404,
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Purchase get successfully',
            'status' => 200,
            'data' => $data,
        ]);
    }

    private function formatMobilePurchase(int $purchaseId, int $profileId): ?array
    {
        $purchase = DB::table('purchases as p')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->where('p.purchase_id', $purchaseId)
            ->where('p.is_deleted', 0)
            ->where('pr.id', $profileId)
            ->select(
                'p.purchase_id',
                'p.purchase_no',
                'p.status',
                'p.supplier_id',
                'p.purchase_date',
                'p.sub_total',
                'p.tax_rate',
                'p.tax_amount',
                'p.shipping_fee',
                'p.total_amount',
                'p.total_paid',
                'p.balance',
                'p.exchange_rate',
                'p.created_at',
                's.supplier_name',
                'u.username as created_by_name'
            )
            ->first();

        if (!$purchase) {
            return null;
        }

        $itemRows = DB::table('purchase_details as pd')
            ->join('items as i', 'pd.item_id', '=', 'i.item_id')
            ->where('pd.purchase_id', $purchaseId)
            ->where('pd.is_deleted', 0)
            ->select(
                'pd.id',
                'pd.item_id',
                'pd.item_cost',
                'pd.unit_price',
                'pd.quantity',
                'pd.subtotal',
                'i.item_name',
                'i.item_code'
            )
            ->orderBy('pd.id', 'asc')
            ->get();

        $items = $itemRows->map(function ($item) {
            $price = (float) ($item->unit_price > 0 ? $item->unit_price : $item->item_cost);

            return [
                'item_id' => (int) $item->id,
                'item_name' => $item->item_name,
                'price' => $price,
                'quantity' => (int) $item->quantity,
                'total' => (float) $item->subtotal,
            ];
        })->values()->toArray();

        $subTotal = (float) $purchase->sub_total;
        $taxPercent = (float) $purchase->tax_rate;
        $taxAmount = (float) $purchase->tax_amount;
        $deliveryFee = (float) $purchase->shipping_fee;
        $grandTotal = (float) $purchase->total_amount;
        $discountAmount = round(max(0, ($subTotal + $taxAmount + $deliveryFee) - $grandTotal), 2);
        $discountPercent = $subTotal > 0 ? round(($discountAmount / $subTotal) * 100, 2) : 0;
        $exchangeRate = (float) $purchase->exchange_rate;

        return [
            'purchase_id' => $purchase->purchase_id,
            'purchase_no' => $purchase->purchase_no,
            'status' => (int) $purchase->status,
            'supplier_id' => (int) $purchase->supplier_id,
            'purchase_date' => $purchase->created_at ?? $purchase->purchase_date,
            'subtotal' => $subTotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'delivery_fee' => $deliveryFee,
            'grand_total' => $grandTotal,
            'paymented' => (float) $purchase->total_paid,
            'balance' => (float) $purchase->balance,
            'exchange_rate' => $exchangeRate,
            'grand_total_khr' => round($grandTotal * $exchangeRate, 2),
            'created_by_name' => $purchase->created_by_name,
            'items' => $items,
        ];
    }

    public function indexMobileRaw(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = DB::table('purchases as p')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->where('p.is_deleted', 0)
            ->where('pr.id', $proId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('p.purchase_no', 'LIKE', "%{$search}%")
                    ->orWhere('s.supplier_name', 'LIKE', "%{$search}%")
                    ->orWhere('u.username', 'LIKE', "%{$search}%");
            });
        }

        $rawPurchases = $query->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('purchase_raw_details as prd')
                    ->whereColumn('prd.purchase_id', 'p.purchase_id')
                    ->where('prd.is_deleted', 0);
            })
            ->select('p.purchase_id')
            ->orderBy('p.purchase_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawPurchases->total() === 0) {
            return response()->json([
                'message' => 'Purchase get successfully',
                'status' => 200,
                'data' => [],
            ]);
        }

        $purchases = [];
        foreach ($rawPurchases as $row) {
            $formatted = $this->formatMobileRawPurchase((int) $row->purchase_id, $proId);
            if ($formatted) {
                $purchases[] = $formatted;
            }
        }

        return response()->json([
            'message' => 'Purchase get successfully',
            'status' => 200,
            'data' => $purchases,
            'pagination' => [
                'current_page' => $rawPurchases->currentPage(),
                'per_page' => $rawPurchases->perPage(),
                'total' => $rawPurchases->total(),
                'last_page' => $rawPurchases->lastPage(),
            ],
        ]);
    }

    public function showMobileRaw($id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $data = $this->formatMobileRawPurchase((int) $id, $proId);
        if (!$data) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status' => 404,
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Purchase get successfully',
            'status' => 200,
            'data' => $data,
        ]);
    }

    private function formatMobileRawPurchase(int $purchaseId, int $profileId): ?array
    {
        $purchase = DB::table('purchases as p')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->leftJoin('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->where('p.purchase_id', $purchaseId)
            ->where('p.is_deleted', 0)
            ->where('pr.id', $profileId)
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('purchase_raw_details as prd')
                    ->whereColumn('prd.purchase_id', 'p.purchase_id')
                    ->where('prd.is_deleted', 0);
            })
            ->select(
                'p.purchase_id',
                'p.purchase_no',
                'p.status',
                'p.supplier_id',
                'p.purchase_date',
                'p.sub_total',
                'p.tax_rate',
                'p.tax_amount',
                'p.shipping_fee',
                'p.total_amount',
                'p.total_paid',
                'p.balance',
                'p.exchange_rate',
                'p.created_at',
                's.supplier_name',
                'u.username as created_by_name'
            )
            ->first();

        if (!$purchase) {
            return null;
        }

        $itemRows = DB::table('purchase_raw_details as prd')
            ->join('raw_materials as rm', 'prd.raw_material_id', '=', 'rm.id')
            ->where('prd.purchase_id', $purchaseId)
            ->where('prd.is_deleted', 0)
            ->select(
                'prd.id',
                'prd.raw_material_id',
                'prd.item_cost',
                'prd.quantity',
                'prd.subtotal',
                'rm.material_name',
                'rm.material_code'
            )
            ->orderBy('prd.id', 'asc')
            ->get();

        $items = $itemRows->map(function ($item) {
            return [
                'item_id' => (int) $item->id,
                'item_name' => $item->material_name,
                'price' => (float) $item->item_cost,
                'quantity' => (int) $item->quantity,
                'total' => (float) $item->subtotal,
            ];
        })->values()->toArray();

        $subTotal = (float) $purchase->sub_total;
        $taxPercent = (float) $purchase->tax_rate;
        $taxAmount = (float) $purchase->tax_amount;
        $deliveryFee = (float) $purchase->shipping_fee;
        $grandTotal = (float) $purchase->total_amount;
        $discountAmount = round(max(0, ($subTotal + $taxAmount + $deliveryFee) - $grandTotal), 2);
        $discountPercent = $subTotal > 0 ? round(($discountAmount / $subTotal) * 100, 2) : 0;
        $exchangeRate = (float) $purchase->exchange_rate;

        return [
            'purchase_id' => $purchase->purchase_id,
            'purchase_no' => $purchase->purchase_no,
            'status' => (int) $purchase->status,
            'supplier_id' => (int) $purchase->supplier_id,
            'purchase_date' => $purchase->created_at ?? $purchase->purchase_date,
            'subtotal' => $subTotal,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'delivery_fee' => $deliveryFee,
            'grand_total' => $grandTotal,
            'paymented' => (float) $purchase->total_paid,
            'balance' => (float) $purchase->balance,
            'exchange_rate' => $exchangeRate,
            'grand_total_khr' => round($grandTotal * $exchangeRate, 2),
            'created_by_name' => $purchase->created_by_name,
            'items' => $items,
        ];
    }

    public function index(Request $request)
    {
        $user  = Auth::user();
        $uid   = $user->id;
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

        $purchases = DB::table('purchases as p')
            ->join('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->select(
                'p.*',
                's.supplier_name',
                'u.username as created_by_name'
            )
            ->where('p.is_deleted', 0)
            // ->where('u.id', $uid)
            ->where('pr.id', $proId)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_details as pd')
                    ->whereColumn('pd.purchase_id', 'p.purchase_id')
                    ->where('pd.is_deleted', 0);
            })

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.purchase_no', 'like', "%{$search}%")
                    ->orWhere('s.supplier_name', 'like', "%{$search}%")
                    ->orWhere('u.username', 'like', "%{$search}%")
                    ->orWhere('p.note', 'like', "%{$search}%");
                });
            })

            ->orderBy('p.purchase_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        if ($purchases->isEmpty()) {
            return response()->json([
                'message' => 'No purchases found!',
                'status'  => 404,
                'data'    => []
            ]);
        }
        // Enrich ONLY current page purchases
        $data = collect($purchases->items())->map(function ($purchase) {
            $details = $this->detailService->purchaseDetail($purchase->purchase_id);

            $payments = DB::table('purchase_payments')
                ->where('purchase_id', $purchase->purchase_id)
                ->where('is_deleted', 0)
                ->get();

            return [
                ...((array) $purchase),
                'details'  => $details,
                'payments' => $payments
            ];
        });

        return response()->json([
            'message' => 'Purchases fetched successfully',
            'status'  => 200,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'per_page'     => $purchases->perPage(),
                'total'        => $purchases->total(),
                'last_page'    => $purchases->lastPage(),
            ]
        ]);
    }


    public function indexRaw(Request $request)
    {
        $user  = Auth::user();
        $uid   = $user->id;
        $proId = $user->profile_id;

        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

        $purchases = DB::table('purchases as p')
            ->join('suppliers as s', 'p.supplier_id', '=', 's.supplier_id')
            ->join('users as u', 'p.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->select(
                'p.*',
                's.supplier_name',
                'u.username as created_by_name'
            )
            ->where('p.is_deleted', 0)
            // ->where('u.id', $uid)
            ->where('pr.id', $proId)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('purchase_raw_details as prd')
                    ->whereColumn('prd.purchase_id', 'p.purchase_id')
                    ->where('prd.is_deleted', 0);
            })

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('p.purchase_no', 'like', "%{$search}%")
                    ->orWhere('s.supplier_name', 'like', "%{$search}%")
                    ->orWhere('u.username', 'like', "%{$search}%");
                });
            })

            ->orderBy('p.purchase_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        if ($purchases->isEmpty()) {
            return response()->json([
                'message' => 'No purchases found!',
                'status'  => 404,
                'data'    => []
            ]);
        }
        // Enrich ONLY current page purchases
        $data = collect($purchases->items())->map(function ($purchase) {
            $details = $this->detailService->purchaseRawDetail($purchase->purchase_id);
            $payments = DB::table('purchase_payments')
                ->where('purchase_id', $purchase->purchase_id)
                ->where('is_deleted', 0)
                ->get();

            return [
                ...((array) $purchase),
                'details'  => $details,
                'payments' => $payments
            ];
        });

        return response()->json([
            'message' => 'Purchases fetched successfully',
            'status'  => 200,
            'data'    => $data->toArray(),
            'pagination' => [
                'current_page' => $purchases->currentPage(),
                'per_page'     => $purchases->perPage(),
                'total'        => $purchases->total(),
                'last_page'    => $purchases->lastPage(),
            ]
        ]);
    }


    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

        $validated = $request->validate([
            'supplier_id'        => 'nullable|integer|exists:suppliers,supplier_id',
            'purchase_date'      => 'required|date',
            'tax_rate'           => 'nullable|numeric',
            'shipping_fee'       => 'nullable|numeric',
            'total_paid'         => 'nullable|numeric',
            'exchange_rate'      => 'nullable|numeric',
            'invoice_number' => 'nullable|string|max:50',
            'status'             => 'required|integer',
            'purchase_type'      => 'required|integer',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.item_cost'  => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date'
        ]);


        $supplierId = $validated['supplier_id'] ?? null;
        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['quantity'] * (float) $item['item_cost'];
        });
        $taxRate = (float) ($validated['tax_rate'] ?? 0);
        $taxAmount = round($subTotal * $taxRate / 100, 2);
        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
        $totalAmount = round($subTotal + $taxAmount + $shippingFee, 2);
        $totalPaid = (float) ($validated['total_paid'] ?? 0);
        $balance = round($totalAmount - $totalPaid, 2);

        $exchange_rate = ExchangeRate::find($proId);
        $now = now();

        $count = Purchase::join('users as u', 'purchases.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->where('pr.id', $proId)
            ->whereYear('purchases.created_at', $now->year)
            ->whereMonth('purchases.created_at', $now->month)
            ->count();
        $purchaseNo = 'PO-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);


        $purchase = Purchase::create([
            'purchase_no'   => $purchaseNo,
            'supplier_id'   => $supplierId ?? Suppliers::max('supplier_id'),
            'purchase_date' => $validated['purchase_date'],
            'sub_total'     => $subTotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'shipping_fee'  => $shippingFee,
            'total_amount'  => $totalAmount,
            'total_paid'    => $totalPaid,
            'balance'       => $balance,
            'purchase_type' => $validated['purchase_type'] ?? 0,
            'exchange_rate' => $exchange_rate->usd_to_khr ?? 4000,
            'invoice_number' => $validated['invoice_number'] ?? '',
            'status'        => $validated['status'],
            'created_by'    => $uid,
        ]);

        $details = [];
        foreach ($validated['items'] as $item) {
            $details[] = PurchaseDetail::create([
                'purchase_id' => $purchase->purchase_id,
                'item_id'     => $item['item_id'],
                'quantity'    => $item['quantity'],
                'item_cost'  => $item['item_cost'],
                'subtotal'    => $item['quantity'] * $item['item_cost'],
            ]);
        }

        $payments = [];
        if (!empty($validated['payments'])) {
            foreach ($validated['payments'] as $payment) {
                $payments[] = PurchasePayment::create([
                    'purchase_id' => $purchase->purchase_id,
                    'amount'      => $payment['amount'],
                    'paid_at'     => $payment['paid_at'],
                    'created_by'  => $uid,
                ]);
            }
        }
        // Update items_cost for each item in the Items table
        foreach ($validated['items'] as $item) {
            $itemData = Items::where('item_id', $item['item_id'])->first();
            $itemData->item_cost = $item['item_cost'];
            $itemData->save();
        }

        return response()->json([
            'message'  => 'Purchase created successfully!',
            'status'   => 201,
        ], 201);
    }


    public function storeRaw(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

        $validated = $request->validate([
            'supplier_id'        => 'nullable|integer|exists:suppliers,supplier_id',
            'purchase_date'      => 'required|date',
            'tax_rate'           => 'nullable|numeric',
            'shipping_fee'       => 'nullable|numeric',
            'total_paid'         => 'nullable|numeric',
            'exchange_rate'      => 'nullable|numeric',
            'invoice_number' => 'nullable|string|max:50',
            'status'             => 'required|integer',
            'purchase_type'      => 'required|integer',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer|exists:items,item_id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.item_cost'  => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date',
        ]);


        $supplierId = $validated['supplier_id'] ?? null;
        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['quantity'] * (float) $item['item_cost'];
        });
        $taxRate = (float) ($validated['tax_rate'] ?? 0);
        $taxAmount = round($subTotal * $taxRate / 100, 2);
        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
        $totalAmount = round($subTotal + $taxAmount + $shippingFee, 2);
        $totalPaid = (float) ($validated['total_paid'] ?? 0);
        $balance = round($totalAmount - $totalPaid, 2);

        $now = now();

        $count = Purchase::join('users as u', 'purchases.created_by', '=', 'u.id')
            ->join('profiles as pr', 'u.profile_id', '=', 'pr.id')
            ->where('pr.id', $proId)
            ->whereYear('purchases.created_at', $now->year)
            ->whereMonth('purchases.created_at', $now->month)
            ->count();
        $purchaseNo = 'RPO-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

        $purchase = Purchase::create([
            'purchase_no'   => $purchaseNo,
            'supplier_id'   => $supplierId ?? Suppliers::max('supplier_id'),
            'purchase_date' => $validated['purchase_date'],
            'sub_total'     => $subTotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'shipping_fee'  => $shippingFee,
            'total_amount'  => $totalAmount,
            'total_paid'    => $totalPaid,
            'balance'       => $balance,
            'purchase_type' => $validated['purchase_type'] ?? 0,
            'exchange_rate' => $validated['exchange_rate'],
            'invoice_number' => $validated['invoice_number'] ?? '',
            'status'        => $validated['status'],
            'created_by'    => $uid,
        ]);

        $details = [];
        foreach ($validated['items'] as $item) {
            $details[] = PurchaseRawDetail::create([
                'purchase_id' => $purchase->purchase_id,
                'raw_material_id'     => $item['item_id'],
                'quantity'    => $item['quantity'],
                'item_cost'  => $item['item_cost'],
                'subtotal'    => $item['quantity'] * $item['item_cost'],
            ]);
        }

        $payments = [];
        if (!empty($validated['payments'])) {
            foreach ($validated['payments'] as $payment) {
                $payments[] = PurchasePayment::create([
                    'purchase_id' => $purchase->purchase_id,
                    'amount'      => $payment['amount'],
                    'paid_at'     => $payment['paid_at'],
                    'created_by'  => $uid,
                ]);
            }
        }
        // // Update items_cost for each item in the Items table
        foreach ($validated['items'] as $item) {
            $itemData = RawMaterial::where('id', $item['item_id'])->first();
            $itemData->material_cost = $item['item_cost'];
            $itemData->save();
        }

        return response()->json([
            'message'  => 'Purchase created successfully!',
            'status'   => 201,
        ], 201);
    }

    public function show($id)
    {
        $purchase = Purchase::where('purchase_id', $id)
            ->where('is_deleted', 0)
            ->first();

        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404,
                'data'    => []
            ]);
        }

        $details = $this->detailService->purchaseDetail($purchase->purchase_id);

        $payments = DB::table('purchase_payments')
            ->select('id', 'amount', 'paid_at')
            ->where('purchase_id', $purchase->purchase_id)
            ->where('is_deleted', 0)
            ->get();

        // Merge purchase info with details + payments
        $data = array_merge(
            $purchase->toArray(),
            [
                'details'  => $details,
                'payments' => $payments
            ]
        );

        return response()->json([
            'message' => 'Purchase fetched successfully!',
            'status'  => 200,
            'data'    => $data
        ]);
    }


    public function showRaw($id)
    {
        $purchase = Purchase::where('purchase_id', $id)
            ->where('is_deleted', 0)
            ->first();

        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404,
                'data'    => []
            ]);
        }


        $details = $this->detailService->purchaseRawDetail($purchase->purchase_id);
        // dd($details);

        $payments = DB::table('purchase_payments')
            ->select('id', 'amount', 'paid_at')
            ->where('purchase_id', $purchase->purchase_id)
            ->where('is_deleted', 0)
            ->get();

        // Merge purchase info with details + payments
        $data = array_merge(
            $purchase->toArray(),
            [
                'details'  => $details,
                'payments' => $payments
            ]
        );

        return response()->json([
            'message' => 'Purchase fetched successfully!',
            'status'  => 200,
            'data'    => $data
        ]);
    }


    public function update(Request $request, $id)
    {
        $purchase = Purchase::find($id);
        if($purchase->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        $user = Auth::user();
        $uid = $user->id;

        if (!$purchase || $purchase->is_deleted) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $validated = $request->validate([
            'supplier_id'   => 'required|integer',
            'purchase_date' => 'required|date',
            'tax_rate'      => 'nullable|numeric',
            'shipping_fee'  => 'nullable|numeric',
            'total_paid'    => 'nullable|numeric',
            'exchange_rate' => 'nullable|numeric',
            'invoice_number' => 'nullable|string|max:50',
            'status'        => 'required|integer',
            'items'         => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer|exists:items,item_id',
            'items.*.quantity'   => 'required|integer',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date'
        ]);

        // return response()->json([
        //     'message' => 'Purchase fetched successfully!',
        //     'status'  => 200,
        //     'data'    => $validated
        // ]);

        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['quantity'] * (float) $item['item_cost'];
        });
        $taxRate = (float) ($validated['tax_rate'] ?? 0);
        $taxAmount = round($subTotal * $taxRate / 100, 2);
        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
        $totalAmount = round($subTotal + $taxAmount + $shippingFee, 2);
        $totalPaid = (float) ($validated['total_paid'] ?? 0);
        $balance = round($totalAmount - $totalPaid, 2);

        $purchase->update([
            'supplier_id'   => $validated['supplier_id'],
            'purchase_date' => $validated['purchase_date'],
            'sub_total'     => $subTotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'shipping_fee'  => $shippingFee,
            'total_amount'  => $totalAmount,
            'total_paid'    => $totalPaid,
            'balance'       => $balance,
            'exchange_rate' => $validated['exchange_rate'] ?? $purchase->exchange_rate,
            'invoice_number' => $validated['invoice_number'] ?? $purchase->invoice_number,
            'status'        => $validated['status'],
        ]);

        PurchaseDetail::where('purchase_id', $id)->delete();

        $details = [];
        foreach ($validated['items'] as $item) {
            $details[] = PurchaseDetail::create([
                'purchase_id' => $id,
                'item_id'     => $item['item_id'],
                'quantity'    => $item['quantity'],
                'item_cost'  => $item['item_cost'],
                // 'attributes'  => json_encode($item['attributes'],true),
                'subtotal'    => $item['quantity'] * $item['item_cost'],
            ]);
        }
        PurchasePayment::where('purchase_id', $id)->delete();

        $payments = [];
        if (!empty($validated['payments'])) {
            foreach ($validated['payments'] as $payment) {
                $payments[] = PurchasePayment::create([
                    'purchase_id' => $id,
                    'amount'      => $payment['amount'],
                    'paid_at'     => $payment['paid_at'],
                    'created_by'  => $uid,
                ]);
            }
        }

        return response()->json([
            'message' => 'Purchase updated successfully',
            'status'  => 200,
        ]);
    }


    public function updateRaw(Request $request, $id)
    {
        $purchase = Purchase::find($id);
        if($purchase->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        $user = Auth::user();
        $uid = $user->id;

        if (!$purchase || $purchase->is_deleted) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $validated = $request->validate([
            'supplier_id'   => 'required|integer',
            'purchase_date' => 'required|date',
            'tax_rate'      => 'nullable|numeric',
            'shipping_fee'  => 'nullable|numeric',
            'total_paid'    => 'nullable|numeric',
            'exchange_rate' => 'nullable|numeric',
            'invoice_number' => 'nullable|string|max:50',
            'status'        => 'required|integer',
            'items'         => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer',
            'items.*.quantity'   => 'required|integer',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date'
        ]);

        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['quantity'] * (float) $item['item_cost'];
        });
        $taxRate = (float) ($validated['tax_rate'] ?? 0);
        $taxAmount = round($subTotal * $taxRate / 100, 2);
        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
        $totalAmount = round($subTotal + $taxAmount + $shippingFee, 2);
        $totalPaid = (float) ($validated['total_paid'] ?? 0);
        $balance = round($totalAmount - $totalPaid, 2);

        $purchase->update([
            'supplier_id'   => $validated['supplier_id'],
            'purchase_date' => $validated['purchase_date'],
            'sub_total'     => $subTotal,
            'tax_rate'      => $taxRate,
            'tax_amount'    => $taxAmount,
            'shipping_fee'  => $shippingFee,
            'total_amount'  => $totalAmount,
            'total_paid'    => $totalPaid,
            'balance'       => $balance,
            'exchange_rate' => $validated['exchange_rate'] ?? $purchase->exchange_rate,
            'invoice_number' => $validated['invoice_number'] ?? $purchase->invoice_number,
            'status'        => $validated['status'],
        ]);

        PurchaseRawDetail::where('purchase_id', $id)->delete();

        $details = [];
        foreach ($validated['items'] as $item) {
            $details[] = PurchaseRawDetail::create([
                'purchase_id' => $id,
                'raw_material_id'     => $item['item_id'],
                'quantity'    => $item['quantity'],
                'item_cost'  => $item['item_cost'],
                'subtotal'    => $item['quantity'] * $item['item_cost'],
            ]);
        }
        PurchasePayment::where('purchase_id', $id)->delete();

        $payments = [];
        if (!empty($validated['payments'])) {
            foreach ($validated['payments'] as $payment) {
                $payments[] = PurchasePayment::create([
                    'purchase_id' => $purchase->purchase_id,
                    'amount'      => $payment['amount'],
                    'paid_at'     => $payment['paid_at'],
                    'created_by'  => $uid,
                ]);
            }
        }

        return response()->json([
            'message' => 'Purchase updated successfully',
            'status'  => 200,
        ]);
    }

    public function destroy($id)
    {
        $purchase = Purchase::find($id);

        if($purchase->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }

        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $purchase->update(['is_deleted' => 1]);
        PurchaseDetail::where('purchase_id', $id)->update(['is_deleted' => 1]);
        PurchaseRawDetail::where('purchase_id', $id)->update(['is_deleted' => 1]);
        PurchasePayment::where('purchase_id', $id)->update(['is_deleted' => 1]);

        return response()->json([
            'message' => 'Purchase deleted successfully!',
            'status'  => 200
        ]);
    }


    public function destroyRaw($id)
    {
        $purchase = Purchase::find($id);
        if($purchase->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $purchase->update(['is_deleted' => 1]);
        PurchaseRawDetail::where('purchase_id', $id)->update(['is_deleted' => 1]);
        PurchaseRawDetail::where('purchase_id', $id)->update(['is_deleted' => 1]);
        PurchasePayment::where('purchase_id', $id)->update(['is_deleted' => 1]);

        return response()->json([
            'message' => 'Purchase deleted successfully!',
            'status'  => 200
        ]);
    }

    public function purchaseCancel($id)
    {
        $purchase = Purchase::find($id);
        if($purchase->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }
        $purchase->update([
            'status' => 2,
        ]);
        return response()->json([
            'message' => 'Purchase canceled successfully',
            'status' => 200,
        ]);
    }
    public function purchaseUncancel($id)
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }
        $purchase->status = 0;
        $purchase->save();
        return response()->json([
            'message' => 'Purchase uncanceled successfully',
            'status' => 200,
        ]);
    }

    public function purchaseConfirm($id)
    {
        $purchaseDB = Purchase::find($id);
        if($purchaseDB->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        $purchase = $this->show($id)->original['data'];
        // dd($purchase);
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        // Use DB transaction for atomicity
        DB::beginTransaction();
        try {
            $purchaseDB->update(['status' => 1]);

            $user = Auth::user();
            $uid = $user->id;
            $proId = $user->profile_id;
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
                'warehouse_id' => $purchaseDB->purchase_type == 0? 1 : 5, // Default or set as needed
                'quantity' => collect($purchase['details'])->sum('quantity'),
                'stock_date' => $stock_date,
                'stock_remark' => 'Purchase Confirmed from ' . $purchase['purchase_no'],
                'stock_created_by' => $uid,
                'is_deleted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stockMasterId = $newStockId;
            // Get purchase details
            // $details = PurchaseDetail::where('purchase_id', $purchase["purchase_id"])->where('is_deleted', 0)->get();
            $details = $purchase['details'];
            // Preload all items in one query for efficiency
            $itemIds = $details->pluck('item_id')->unique()->toArray();
            $itemsMap = Items::whereIn('item_id', $itemIds)->get()->keyBy('item_id');

            $exchange_rate = ExchangeRate::find($proId);
            $stockItems = [];
            foreach ($details as $item) {
                $itemData = $itemsMap[$item->item_id] ?? null;
                if (!$itemData) continue; // skip if item not found
                $stockItems[] = StockDetails::create([
                    'stock_id' => (int)$stockMasterId,
                    'item_id' => $item->item_id,
                    'quantity' => (int)$item->quantity,
                    'item_cost' => $item->item_cost,
                    'expire_date' => null, // Set if available
                    'transection_date' => $stock_date,
                    'is_deleted' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Items::find($item->item_id)->update([
                    'cost_price' => (int)$item->item_cost,
                ]);

            }

            DB::commit();
            return response()->json([
                'message' => 'Purchase confirmed and items inserted into stock successfully',
                'status' => 200,
                'data' => $purchase,
                'stock_master_id' => $stockMasterId,
                'stock_items' => $stockItems
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error confirming purchase: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }


    public function purchaseConfirmRaw($id)
    {
        $purchaseDB = Purchase::find($id);
        if($purchaseDB->status == 1){
            return response()->json([
                'message' => 'Purchase is already completed!',
                'status'  => 400
            ]);
        }
        $purchase = $this->showRaw($id)->original['data'];
        // dd($purchase);
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        // Use DB transaction for atomicity
        DB::beginTransaction();
        try {
            $purchaseDB->update(['status' => 1]);

            $user = Auth::user();
            $uid = $user->id;
            $proId = $user->profile_id;
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
            $stock_no = 'RIN-' . now()->format('Ymd') . '-' . str_pad($count + 1, 5, '0', STR_PAD_LEFT);

            // Create stock master
            DB::table('stock_masters')->insert([
                'stock_id' => $newStockId,
                'stock_no' => $stock_no,
                'stock_type_id' => 2, // 2 = stock in
                'from_warehouse' => 2, // Default or set as needed
                'warehouse_id' => $purchaseDB->purchase_type == 0? 1 : 5, // Default or set as needed
                'quantity' => collect($purchase['details'])->sum('quantity'),
                'stock_date' => $stock_date,
                'stock_remark' => 'Purchase Confirmed from '. $purchase['purchase_no'],
                'stock_created_by' => $uid,
                'is_deleted' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $stockMasterId = $newStockId;
            // Get purchase details
            // $details = PurchaseDetail::where('purchase_id', $purchase["purchase_id"])->where('is_deleted', 0)->get();
            $details = $purchase['details'];
            // Preload all items in one query for efficiency
            $itemIds = $details->pluck('raw_material_id')->unique()->toArray();
            $itemsMap = RawMaterial::whereIn('id', $itemIds)->get()->keyBy('id');

            $exchange_rate = ExchangeRate::find($proId);
            $stockItems = [];
            foreach ($details as $item) {
                $itemData = $itemsMap[$item->raw_material_id] ?? null;
                if (!$itemData) continue; // skip if item not found
                $stockItems[] = StockRawDetail::create([
                    'stock_id' => (int)$stockMasterId,
                    'raw_material_id' => $item->raw_material_id,
                    'quantity' => (int)$item->quantity,
                    'item_cost' => $item->item_cost,
                    'expire_date' => null, // Set if available
                    'transection_date' => $stock_date,
                    'is_deleted' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            }

            DB::commit();
            return response()->json([
                'message' => 'Purchase confirmed and items inserted into stock successfully',
                'status' => 200,
                'data' => $purchase,
                'stock_master_id' => $stockMasterId,
                'stock_items' => $stockItems
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error confirming purchase: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }


    public function purchasePayment(Request $request, $id)
    {
        $purchase = Purchase::find($id);
        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $validated = $request->validate([
            'amount'  => 'required|numeric|min:0',
            'paid_at' => 'required|date'
        ]);

        $user = Auth::user();
        $uid = $user->id;
        if($validated['amount'] <= 0){
            return response()->json([
                'message' => 'Payment amount must be greater than zero',
                'status'  => 400
            ], 400);
        }
        if($validated['amount'] > $purchase->balance){
            return response()->json([
                'message' => 'Payment amount exceeds remaining balance',
                'status'  => 400
            ], 400);
        }

        PurchasePayment::create([
            'purchase_id' => $id,
            'amount'      => $validated['amount'],
            'paid_at'     => $validated['paid_at'],
            'created_by'  => $uid,
        ]);

        // Update total_paid and balance in purchases table
        $purchase->total_paid += $validated['amount'];
        $purchase->balance = $purchase->total_amount - $purchase->total_paid;
        $purchase->save();

        return response()->json([
            'message' => 'Payment added successfully',
            'status'  => 200,
        ], 200);
    }
}
