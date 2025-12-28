<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class ReportController extends Controller
{
    public function saleReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $request->validate([
            'order_customer' => 'nullable|integer',
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
            // ->join('items as i', 'oi.item_id', '=', 'i.item_id')
            // ->join('categories as cg', 'i.category_id', '=', 'cg.category_id')
            // ->join('brands as br', 'i.brand_id', '=', 'br.brand_id')
            // ->join('scales as sc', 'i.scale_id', '=', 'sc.scale_id')
            // ->join('sizes as sz', 'i.size_id', '=', 'sz.size_id')
            ->where('p.id', $proId);

        // Filter by customer if provided
        if ($request->filled('order_customer')) {
            $query->where('c.customer_id', $request->order_customer);
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

    public function saleReportByItem(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'order_customer' => 'nullable|integer',
            'item_id' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = \DB::table('order_items as oi')
            ->select(
                'i.item_id',
                'i.barcode',
                'i.item_name',
                'oi.quantity',
                'i.item_price',
                'cg.category_name',
                'br.brand_name',
                'oi.price',
                'om.order_discount',
                'om.order_date',
                'c.customer_name as order_customer'
            )
            ->join('order_masters as om', 'om.order_id', '=', 'oi.order_id')
            ->join('customers as c','c.customer_id','=','om.order_customer_id')
            ->join('users as u', 'u.id', '=', 'om.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->join('items as i', 'oi.item_id', '=', 'i.item_id')
            ->join('categories as cg', 'i.category_id', '=', 'cg.category_id')
            ->join('brands as br', 'i.brand_id', '=', 'br.brand_id')
            ->join('scales as sc', 'i.scale_id', '=', 'sc.scale_id')
            ->where('p.id', $proId);

        // Filter by customer if provided
        if ($request->filled('order_customer')) {
            $query->where('c.customer_id', $request->order_customer);
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


    public function expanseReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $request->validate([
            'expanse_by' => 'nullable|string',
            'expanse_type_id' => 'nullable|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $query = \DB::table('expanse_items as ei')
            ->select(
                'em.expanse_no',
                'em.expanse_date',
                'em.expanse_by',
                'em.expanse_supplier',
                'et.expanse_type_name',
                'ei.quantity',
                'ei.unit_price',
                'ei.sub_total',
                'em.created_by'
            )
            ->join('expanse_masters as em', 'ei.expanse_id', '=', 'em.expanse_id')
            ->join('expanse_types as et', 'ei.expanse_type_id', '=', 'et.expanse_type_id')
            ->join('users as u', 'u.id', '=', 'em.created_by')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId);

        // Filter by expanse_by if provided
        if ($request->filled('expanse_by')) {
            $query->where('em.expanse_by', 'like', '%' . $request->expanse_by . '%');
        }

        // Filter by expanse_type_id if provided
        if ($request->filled('expanse_type_id')) {
            $query->where('ei.expanse_type_id', $request->expanse_type_id);
        }

        // Filter by date range if provided
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('em.expanse_date', [$request->start_date, $request->end_date]);
        } elseif ($request->filled('start_date')) {
            $query->where('em.expanse_date', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $query->where('em.expanse_date', '<=', $request->end_date);
        }

        $results = $query->get();

        return response()->json([
            'message' => 'expanse report get successfully',
            'status' => 200,
            'data' => $results
        ]);
    }

    public function purchaseReport(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'supplier_id' => 'nullable|integer|exists:suppliers,supplier_id'
        ]);

        $query = Purchase::with(['details.item', 'payments', 'supplier', 'users'])
            ->where('is_deleted', 0)
            ->whereHas('users', function ($query) use ($proId) {
                $query->where('profile_id', $proId);
            });

        // Apply supplier filter if provided
        if (isset($validated['supplier_id'])) {
            $query->where('supplier_id', $validated['supplier_id']);
        }

        // Determine date range
        $startDate = $validated['start_date'] ?? '';
        $endDate = $validated['end_date'] ?? '';

        if ($startDate || $endDate) {
            // If start_date is empty, use the earliest purchase date
            $startDate = $startDate ?: Purchase::where('is_deleted', 0)->min('purchase_date') ?: date('Y-m-d');
            // If end_date is empty, use the latest purchase date
            $endDate = $endDate ?: Purchase::where('is_deleted', 0)->max('purchase_date') ?: date('Y-m-d');
            // Ensure end_date is not before start_date
            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    'message' => 'End date cannot be before start date.',
                    'status' => 422
                ], 422);
            }
            $query->whereBetween('purchase_date', [$startDate, $endDate]);
        }

        $purchases = $query->get();

        return response()->json([
            'message' => 'Purchase report generated successfully!',
            'status' => 200,
            'data' => $purchases->map(function ($purchase) {
                return [
                    'purchase_id' => $purchase->purchase_id,
                    'purchase_no' => $purchase->purchase_no,
                    'supplier_id' => $purchase->supplier_id,
                    'supplier_name' => $purchase->supplier ? $purchase->supplier->supplier_name : 'Unknown',
                    'supplier_address' => $purchase->supplier ? $purchase->supplier->supplier_address : 'N/A',
                    'supplier_tel' => $purchase->supplier ? $purchase->supplier->supplier_tel : 'N/A',
                    'supplier_email' => $purchase->supplier ? $purchase->supplier->supplier_email : 'N/A',
                    'purchase_date' => $purchase->purchase_date,
                    'sub_total' => number_format($purchase->sub_total, 2, '.', ''),
                    'tax_rate' => number_format($purchase->tax_rate, 2, '.', ''),
                    'tax_amount' => number_format($purchase->tax_amount, 2, '.', ''),
                    'shipping_fee' => number_format($purchase->shipping_fee, 2, '.', ''),
                    'total_amount' => number_format($purchase->total_amount, 2, '.', ''),
                    'total_paid' => number_format($purchase->total_paid, 2, '.', ''),
                    'balance' => number_format($purchase->balance, 2, '.', ''),
                    'exchange_rate' => number_format($purchase->exchange_rate, 2, '.', ''),
                    'status' => $purchase->status,
                    'is_deleted' => $purchase->is_deleted,
                    'created_by' => $purchase->created_by,
                    'created_at' => $purchase->created_at,
                    'updated_at' => $purchase->updated_at,
                    'details' => $purchase->details->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'item_id' => $detail->item_id,
                            'quantity' => number_format($detail->quantity, 2, '.', ''),
                            'unit_price' => number_format($detail->unit_price, 2, '.', ''),
                            'subtotal' => number_format($detail->subtotal, 2, '.', ''),
                            'item_name' => $detail->item ? $detail->item->item_name : 'Unknown',
                            'item_code' => $detail->item ? $detail->item->item_code : 'N/A',
                            'item_price' => $detail->item ? number_format($detail->item->item_price, 2, '.', '') : '0.00'
                        ];
                    }),
                    'payments' => $purchase->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => number_format($payment->amount, 2, '.', ''),
                            'paid_at' => $payment->paid_at
                        ];
                    })
                ];
            })
        ], 200);
    }

    public function purchaseReportByUser(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'created_by' => 'nullable|integer|exists:users,id',
            'supplier_id' => 'nullable|integer'
        ]);

        $query = Purchase::with(['details.item', 'payments', 'users', 'supplier'])
            ->where('is_deleted', 0)
            ->whereHas('users', function ($query) use ($proId) {
                $query->where('profile_id', $proId);
            });

        // Apply created_by filter if provided
        if (isset($validated['created_by'])) {
            $query->where('created_by', $validated['created_by']);
        }
        if(isset($validated['supplier_id'])){
            $query->whereHas('supplier', function ($q) use ($validated) {
                $q->where('supplier_id', $validated['supplier_id']);
            });
        }

        // Determine date range
        $startDate = $validated['start_date'] ?? '';
        $endDate = $validated['end_date'] ?? '';

        if ($startDate || $endDate) {
            // If start_date is empty, use the earliest purchase date
            $startDate = $startDate ?: Purchase::where('is_deleted', 0)->min('purchase_date') ?: date('Y-m-d');
            // If end_date is empty, use the latest purchase date
            $endDate = $endDate ?: Purchase::where('is_deleted', 0)->max('purchase_date') ?: date('Y-m-d');
            // Ensure end_date is not before start_date
            if (strtotime($endDate) < strtotime($startDate)) {
                return response()->json([
                    'message' => 'End date cannot be before start date.',
                    'status' => 422
                ], 422);
            }
            $query->whereBetween('purchase_date', [$startDate, $endDate]);
        }

        $purchases = $query->get();

        return response()->json([
            'message' => 'Purchase report generated successfully!',
            'status' => 200,
            'data' => $purchases->map(function ($purchase) {
                return [
                    'barcode' => $purchase->details->map(function ($detail) {
                        return $detail->item ? $detail->item->barcode : 'N/A';
                    })->first(),
                    'supplier_name' => $purchase->supplier->supplier_name,
                    'supplier_tel' => $purchase->supplier->supplier_tel,
                    'purchase_date' => $purchase->purchase_date,
                    'created_by' => $purchase->users->username,
                    'shipping_fee' => number_format($purchase->shipping_fee, 2, '.', ''),
                    'tax_amount' => number_format($purchase->tax_amount, 2, '.', ''),
                    'total_amount' => number_format($purchase->total_amount, 2, '.', ''),
                    'total_paid' => number_format($purchase->total_paid, 2, '.', ''),
                    'balance' => number_format($purchase->balance, 2, '.', '')
                ];
            })
        ], 200);
    }
}
