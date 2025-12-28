<?php

namespace App\Http\Controllers;

use App\Models\Items;
use App\Models\Purchase;
use App\Models\PurchaseDetail;
use App\Models\Purchases;
use App\Models\PurchaseDetails;
use App\Models\PurchasePayment;
use App\Models\PurchasePayments;
use App\Models\PurchaseAttribute;
use App\Models\StockDetails;
use App\Models\StockAttribute;
use App\Models\Suppliers;
use App\Services\DetailService;
use App\Models\ExchangeRate;
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
    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;

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
            ->where('u.id', $uid)
            ->where('pr.id', $proId)
            ->get();

        if ($purchases->isEmpty()) {
            return response()->json([
                'message' => 'No purchases found!',
                'status'  => 404,
                'data'    => []
            ]);
        }

        $data = $purchases->map(function ($purchase) {

            $details = $this->detailService->purchaseDetail($purchase->purchase_id);

            $payments = DB::table('purchase_payments')
                ->where('purchase_id', $purchase->purchase_id)
                ->where('is_deleted', 0)
                ->get();

            return [
                ...((array)$purchase),
                'details'  => $details,
                'payments' => $payments
            ];
        });

        return response()->json([
            'message' => 'Purchases fetched successfully',
            'status'  => 200,
            'data'    => array_reverse($data->toArray())
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;

        $validated = $request->validate([
            'supplier_id'        => 'nullable|integer|exists:suppliers,supplier_id',
            'purchase_date'      => 'required|date',
            'sub_total'          => 'required|numeric',
            'tax_rate'           => 'nullable|numeric',
            'tax_amount'         => 'nullable|numeric',
            'shipping_fee'       => 'nullable|numeric',
            'total_amount'       => 'required|numeric',
            'total_paid'         => 'nullable|numeric',
            'balance'            => 'nullable|numeric',
            'exchange_rate'      => 'nullable|numeric',
            'status'             => 'required|integer',
            'items'              => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date'
        ]);



        $purchaseNo = 'PO-' . now()->format('Ymd') . '-' . str_pad((Purchase::max('purchase_id') + 1), 5, '0', STR_PAD_LEFT);

        $purchase = Purchase::create([
            'purchase_no'   => $purchaseNo,
            'supplier_id'   => $supplierId ?? Suppliers::max('supplier_id'),
            'purchase_date' => $validated['purchase_date'],
            'sub_total'     => $validated['sub_total'],
            'tax_rate'      => $validated['tax_rate'] ?? 0,
            'tax_amount'    => $validated['tax_amount'] ?? 0,
            'shipping_fee'  => $validated['shipping_fee'] ?? 0,
            'total_amount'  => $validated['total_amount'],
            'total_paid'    => $validated['total_paid'] ?? 0,
            'balance'       => $validated['balance'] ?? 0,
            'exchange_rate' => $validated['exchange_rate'] ?? 1,
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
            'data'     => $purchase,
            'details'  => $details,
            'payments' => $payments
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


    public function update(Request $request, $id)
    {
        $purchase = Purchase::find($id);
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
            'sub_total'     => 'required|numeric',
            'tax_rate'      => 'nullable|numeric',
            'tax_amount'    => 'nullable|numeric',
            'shipping_fee'  => 'nullable|numeric',
            'total_amount'  => 'required|numeric',
            'total_paid'    => 'nullable|numeric',
            'balance'       => 'nullable|numeric',
            'exchange_rate' => 'nullable|numeric',
            'status'        => 'required|integer',
            'items'         => 'required|array|min:1',
            'items.*.item_id'    => 'required|integer',
            'items.*.quantity'   => 'required|integer',
            'items.*.item_cost' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date'
        ]);

        $purchase->update($validated);

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
            'data'    => $purchase,
            'details' => $details
        ]);
    }

    public function destroy($id)
    {
        $purchase = Purchase::find($id);

        if (!$purchase) {
            return response()->json([
                'message' => 'Purchase not found!',
                'status'  => 404
            ]);
        }

        $purchase->update(['is_deleted' => 1]);
        PurchaseDetail::where('purchase_id', $id)->update(['is_deleted' => 1]);
        PurchasePayment::where('purchase_id', $id)->update(['is_deleted' => 1]);

        return response()->json([
            'message' => 'Purchase deleted successfully!',
            'status'  => 200
        ]);
    }

    public function purchaseCancel($id)
    {
        $purchase = Purchase::find($id);
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
            'data' => $purchase
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
            'data' => $purchase
        ]);
    }

    public function purchaseConfirm($id)
    {
        $purchaseDB = Purchase::find($id);
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
            $stock_no = now()->format('Ymd') . '-' . str_pad(($maxStockId), 5, '0', STR_PAD_LEFT);

            // Create stock master
            DB::table('stock_masters')->insert([
                'stock_id' => $newStockId,
                'stock_no' => $stock_no,
                'stock_type_id' => 2, // 2 = stock in
                'from_warehouse' => 2, // Default or set as needed
                'warehouse_id' => 1, // Default or set as needed
                'order_id' => null,
                'stock_date' => $stock_date,
                'stock_remark' => 'Purchase Confirmed',
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
                    'item_cost' => (int)$item->item_cost,
                    'expire_date' => null, // Set if available
                    'transection_date' => $stock_date,
                    'is_deleted' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $detailId = StockDetails::max("detail_id");

                // dd($detailId);
                $purchase_details = $item->attributes;

                foreach($purchase_details as $attr){
                    StockAttribute::create([
                        'stock_detail_id'=> $detailId,
                        'item_id' =>$item->item_id,
                        'attribute_id' =>$attr->name_id,
                        'attribute_value_id' =>$attr->value_id,
                    ]);
                };
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

        $payment = PurchasePayment::create([
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
            'data'    => $payment,
            'purchase'=> $purchase
        ], 200);
    }
}
