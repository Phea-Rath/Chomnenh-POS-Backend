<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\OnlineEvent;
use App\Events\OrderMessage;
use App\Events\PrivateChannelEvent;
use App\Models\OrderItems;
use App\Models\Customers;
use App\Models\OrderMaster;
use App\Models\ExchangeRate;
use App\Models\Items;
use App\Models\OrderAttribute;
use App\Models\OrderPayment;
use App\Models\OrderTracking;
use App\Models\Payment;
use App\Models\Users;
use App\Services\DetailService;
use App\Services\AttributeService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ItemService;
use App\Services\TelegramService;
use Carbon\Carbon;

class OrderMasterController extends Controller
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

    public function indexMobile(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = (int) $request->input('limit', 10);
        $page = (int) $request->input('page', 1);
        $search = $request->input('search');

        $query = DB::table('order_masters as om')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->leftJoin('customers as cu', 'om.order_customer_id', '=', 'cu.customer_id')
            ->leftJoin('delivers as dl', 'om.deliver_id', '=', 'dl.deliver_id')
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->where('p.id', $proId);

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('om.order_no', 'LIKE', "%{$search}%")
                    ->orWhere('cu.customer_name', 'LIKE', "%{$search}%")
                    ->orWhere('cu.customer_email', 'LIKE', "%{$search}%")
                    ->orWhere('cu.customer_tel', 'LIKE', "%{$search}%")
                    ->orWhere('dl.deliver_name', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('customer_id')) {
            $query->where('om.order_customer_id', $request->customer_id);
        }
        if ($request->filled('deliver_id')) {
            $query->where('om.deliver_id', $request->deliver_id);
        }
        if ($request->filled('user_id')) {
            $query->where('om.created_by', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('om.status', $request->status);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('om.order_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('om.order_date', '<=', $request->end_date);
        }

        $rawOrders = $query->select('om.order_id')
            ->orderBy('om.order_id', 'DESC')
            ->paginate($limit, ['*'], 'page', $page);

        if ($rawOrders->total() === 0) {
            return response()->json([
                'success' => false,
                'status_code' => 404,
                'data' => [
                    'orders' => [],
                    'pagination' => [
                        'current_page' => $rawOrders->currentPage(),
                        'per_page' => $rawOrders->perPage(),
                        'total' => $rawOrders->total(),
                        'last_page' => $rawOrders->lastPage(),
                    ],
                ],
            ], 404);
        }

        $orders = [];
        foreach ($rawOrders as $row) {
            $formatted = $this->formatMobileOrder((int) $row->order_id, $proId);
            if ($formatted) {
                $orders[] = $formatted;
            }
        }

        return response()->json([
            'message' => 'Orders retrieved successfully',
            'status' => 200,
            'data' => [
                'orders' => $orders,
                'pagination' => [
                    'current_page' => $rawOrders->currentPage(),
                    'per_page' => $rawOrders->perPage(),
                    'total' => $rawOrders->total(),
                    'last_page' => $rawOrders->lastPage(),
                ],
            ],
        ]);
    }
    public function showMobile($id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $data = $this->formatMobileOrder((int) $id, $proId);
        if (!$data) {
            return response()->json([
                'message' => 'Order not found',
                'status' => 404,
                'data' => null,
            ], 404);
        }

        return response()->json([
            'message' => 'Order retrieved successfully',
            'status' => 200,
            'data' => $data,
        ]);

    }

    private function formatMobileOrder(int $orderId, int $profileId): ?array
    {
        $order = DB::table('order_masters as om')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->leftJoin('customers as cu', 'om.order_customer_id', '=', 'cu.customer_id')
            ->leftJoin('delivers as dl', 'om.deliver_id', '=', 'dl.deliver_id')
            ->where('om.order_id', $orderId)
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->where('p.id', $profileId)
            ->select(
                'om.*',
                'cu.customer_id',
                'cu.customer_name',
                'cu.customer_email',
                'cu.customer_tel',
                'cu.customer_address',
                'u.username as created_by_name',
                'dl.deliver_id',
                'dl.deliver_name',
                'dl.image as deliver_image'
            )
            ->first();

        if (!$order) {
            return null;
        }

        $orderItems = DB::table('order_items as oi')
            ->leftJoin('items as i', 'oi.item_id', '=', 'i.item_id')
            ->leftJoin('item_images as ii', 'ii.item_id', '=', 'i.item_id')
            ->leftJoin('images as im', 'im.id', '=', 'ii.image_id')
            ->where('oi.order_id', $orderId)
            ->where('oi.is_deleted', 0)
            ->select(
                'oi.id',
                'oi.item_id',
                'oi.item_price',
                'oi.quantity',
                'oi.discount',
                'oi.price',
                'i.item_code',
                'i.item_name',
                DB::raw('MIN(im.id) as first_image_id'),
                DB::raw('MIN(im.image) as first_image')
            )
            ->groupBy(
                'oi.id',
                'oi.item_id',
                'oi.item_price',
                'oi.quantity',
                'oi.discount',
                'oi.price',
                'i.item_code',
                'i.item_name'
            )
            ->orderBy('oi.id', 'asc')
            ->get();

        $items = $orderItems->map(function ($item) {
            return [
                'id' => (int) $item->id,
                'product_name' => $item->item_name,
                'item_code' => $item->item_code,
                'price' => (float) $item->item_price,
                'quantity' => (int) $item->quantity,
                'discount' => (float) $item->discount,
                'total' => (float) $item->price,
                'image' => $item->first_image ? url('storage/images/' . basename($item->first_image)) : null,
            ];
        })->values()->toArray();

        $deliveryImage = $order->deliver_image ? url('storage/images/' . basename($order->deliver_image)) : null;
        $grandTotalUsd = (float) $order->order_total;
        $exchangeRate = (float) $order->exchange_rate;
        $payments = $this->detailService->orderPayment($order->order_id);

        // dd($payments);

        $amount = $payments->sum('amount');

        return [
            'order_header' => [
                'id' => (int) $order->order_id,
                'online' => (bool) $order->online,
                'through' => null,
                'order_no' => $order->order_no,
                'sale_type' => $order->sale_type,
                'exchange_rate' => $exchangeRate,
                'payment_method' => $payments[count($payments) - 1]->payment_method,
                'created_by_name' => $order->created_by_name,
                'date' => $order->created_at,
            ],
            'pricing_summary' => [
                'base_currency' => 'USD',
                'exchange_rate' => $exchangeRate,
                'subtotal' => (float) $order->order_subtotal,
                'discount_amount' => (float) $order->order_discount,
                'tax_amount' => (float) $order->order_tax,
                'delivery_fee' => (float) $order->delivery_fee,
                'paymented' => (float) $amount,
                'balance' => (float) $order->balance,
                'grand_total_usd' => $grandTotalUsd,
                'grand_total_khr' => round($grandTotalUsd * $exchangeRate, 2),
                'order_payment_status' => $order->order_payment_status,
            ],
            'items' => $items,
            'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
            // 'customer' => [
            //     'id' => $order->customer_id ? (int) $order->customer_id : null,
            //     'name' => $order->customer_name,
            //     'email' => $order->customer_email,
            //     'phone' => $order->customer_tel ?? $order->order_tel,
            //     'address' => $order->customer_address ?? $order->order_address,
            // ],
            'delivery_id' => $order->deliver_id ? (int) $order->deliver_id : null,
            // 'delivery' => [
            //     'id' => $order->deliver_id ? (int) $order->deliver_id : null,
            //     'deliver_name' => $order->deliver_name,
            //     'image' => $deliveryImage,
            // ],
        ];
    }

    public function index(Request $request)
{
    $user  = Auth::user();
    $proId = $user->profile_id;

    $limit  = $request->input('limit', 10);
    $page   = $request->input('page', 1);
    $search = $request->input('search'); // 🔍 search keyword

    $orderMasters = DB::table('order_masters as om')
        ->join('customers as cu', 'om.order_customer_id', '=', 'cu.customer_id')
        ->join('delivers as dl', 'om.deliver_id', '=', 'dl.deliver_id')
        ->join('users as u', 'om.created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->where('om.is_deleted', 0)
        ->where('om.is_active', 1)
        ->whereNull('om.reference_no')
        ->where('p.id', $proId)

        // 🔍 SEARCH FILTER
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('om.order_no', 'like', "%{$search}%")
                  ->orWhere('cu.customer_name', 'like', "%{$search}%")
                  ->orWhere('cu.customer_email', 'like', "%{$search}%")
                  ->orWhere('dl.deliver_name', 'like', "%{$search}%");
            });
        })
        ->when($request->input('customer_id'), function ($query, $customer_id) {
            $query->where('om.order_customer_id', $customer_id);
        })
        ->when($request->input('deliver_id'), function ($query, $deliver_id) {
            $query->where('om.deliver_id', $deliver_id);
        })
        ->when($request->input('user_id'), function ($query, $user_id) {
            $query->where('om.created_by', $user_id);
        })
        ->when($request->input('status'), function ($query, $status) {
            $query->where('om.status', $status);
        })
        ->when($request->input('category_id'), function ($query, $category_id) {
            $query->whereExists(function ($subQuery) use ($category_id) {
                $subQuery->select(DB::raw(1))
                    ->from('order_items as oi')
                    ->join('items as i', 'oi.item_id', '=', 'i.item_id')
                    ->whereColumn('oi.order_id', 'om.order_id')
                    ->where('i.category_id', $category_id)
                    ->where('oi.is_deleted', 0);
            });
        })
        ->when($request->input('brand_id'), function ($query, $brand_id) {
            $query->whereExists(function ($subQuery) use ($brand_id) {
                $subQuery->select(DB::raw(1))
                    ->from('order_items as oi')
                    ->join('items as i', 'oi.item_id', '=', 'i.item_id')
                    ->whereColumn('oi.order_id', 'om.order_id')
                    ->where('i.brand_id', $brand_id)
                    ->where('oi.is_deleted', 0);
            });
        })
        ->when($request->input('start_date'), function ($query, $start_date) {
            $query->whereDate('om.order_date', '>=', $start_date);
        })
        ->when($request->input('end_date'), function ($query, $end_date) {
            $query->whereDate('om.order_date', '<=', $end_date);
        })

        ->select(
            'cu.customer_name',
            'cu.customer_email',
            'dl.deliver_name',
            'dl.image as deliver_image',
            'om.*'
        )
        ->orderBy('om.order_id', 'desc')
        ->paginate($limit, ['*'], 'page', $page);

    if ($orderMasters->isEmpty()) {
        return response()->json([
            'message' => 'Order masters not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    // Fix deliver image URL
    foreach ($orderMasters as $item) {
        if ($item->deliver_image) {
            $filenameOnly = basename($item->deliver_image);
            $item->deliver_image = url('storage/images/' . $filenameOnly);
        }

    }

    // Attach items to each order (current page only)
    $ordersWithItems = collect($orderMasters->items())->map(function ($order) {
        $order->items = $this->detailService->orderDetailById($order->order_id);
        $order->payments = $this->detailService->orderPayment($order->order_id);
        return $order;
    });

    return response()->json([
        'message' => 'Order masters fetched successfully!',
        'status'  => 200,
        'data'    => $ordersWithItems->toArray(),
        'pagination' => [
            'current_page' => $orderMasters->currentPage(),
            'per_page'     => $orderMasters->perPage(),
            'total'        => $orderMasters->total(),
            'last_page'    => $orderMasters->lastPage(),
        ]
    ]);
}


    public function OrderInvoices(Request $request)
    {
        $user  = Auth::user();
        $proId = $user->profile_id;

        $created_by = $request->input('created_by');
        $customer_id = $request->input('customer_id');
        $start_date = $request->input('start_date');
        $end_date = $request->input('end_date');
        $item_for = $request->input('item_for');
        $limit  = $request->input('limit', 10);
        $page   = $request->input('page', 1);
        $search = $request->input('search'); // 🔍 search keyword

        $orderMasters = DB::table('order_masters as om')
            ->join('customers as cu', 'om.order_customer_id', '=', 'cu.customer_id')
            ->join('delivers as dl', 'om.deliver_id', '=', 'dl.deliver_id')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->where('p.id', $proId)
            ->where('om.sale_type', 'wholesale') // Only fetch orders with sale_type = 'sale'
            ->when($created_by, function ($query) use ($created_by) {
                $query->where('om.created_by', $created_by);
            })
            ->when($customer_id, function ($query) use ($customer_id) {
                $query->where('om.order_customer_id', $customer_id);
            })
            ->when($start_date, function ($query) use ($start_date) {
                $query->whereDate('om.order_date', '>=', $start_date);
            })
            ->when($end_date, function ($query) use ($end_date) {
                $query->whereDate('om.order_date', '<=', $end_date);
            })
            ->when($item_for, function ($query) use ($item_for) {
                $query->whereExists(function ($subQuery) use ($item_for) {
                    $subQuery->select(DB::raw(1))
                        ->from('order_items as oi')
                        ->whereColumn('oi.order_id', 'om.order_id')
                        ->where('oi.item_for', $item_for)
                        ->where('oi.is_deleted', 0);
                });
            })

            // 🔍 SEARCH FILTER
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('om.order_no', 'like', "%{$search}%")
                    ->orWhere('cu.customer_name', 'like', "%{$search}%")
                    ->orWhere('cu.customer_email', 'like', "%{$search}%")
                    ->orWhere('dl.deliver_name', 'like', "%{$search}%");
                });
            })

            ->select(
                'cu.customer_name',
                'cu.customer_email',
                'u.username as created_by_name',
                'dl.deliver_name',
                'dl.image as deliver_image',
                'om.*'
            )
            ->orderBy('om.order_id', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        if ($orderMasters->isEmpty()) {
            return response()->json([
                'message' => 'Order masters not found!',
                'status'  => 404,
                'data'    => []
            ]);
        }

        // Fix deliver image URL
        foreach ($orderMasters as $item) {
            if ($item->deliver_image) {
                $filenameOnly = basename($item->deliver_image);
                $item->deliver_image = url('storage/images/' . $filenameOnly);
            }
        }

        // Attach items to each order (current page only)
        $ordersWithItems = collect($orderMasters->items())->map(function ($order) {
            $order->items = $this->detailService->orderDetailById($order->order_id);
            $order->payments = $this->detailService->orderPayment($order->order_id);
            return $order;
        });

        return response()->json([
            'message' => 'Order masters fetched successfully!',
            'status'  => 200,
            'data'    => $ordersWithItems->toArray(),
            'pagination' => [
                'current_page' => $orderMasters->currentPage(),
                'per_page'     => $orderMasters->perPage(),
                'total'        => $orderMasters->total(),
                'last_page'    => $orderMasters->lastPage(),
            ]
        ]);
    }

    public function orderByUser($id)
    {
        $user = auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $orderMasters = DB::table('order_masters as om')
            ->join('customers as cu','om.order_customer_id','=',"cu.customer_id")
            ->join('delivers as dl','om.deliver_id','=',"dl.deliver_id")
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('users as ut', 'om.through', '=', 'ut.id')
            ->where('om.is_deleted', 0)
            ->where('om.created_by', $id)
            ->where('u.profile_id', $proId)
            ->where('ut.profile_id', $proId)
            ->where('om.is_active', 1)
            ->select('cu.customer_name','cu.customer_email', 'dl.deliver_name', 'dl.image as deliver_image',"om.*")->get();

        foreach ($orderMasters as $item) {
            if ($item->deliver_image) {
                $filenameOnly = basename($item->deliver_image);
                $item->deliver_image = url('storage/images/' . $filenameOnly);
            }
        }

        if ($orderMasters->isEmpty()) {
            return response()->json([
                'message' => 'Order masters get fail!',
                'status' => 404,
            ]);
        }

        // Attach items to each order
        $ordersWithItems = $orderMasters->map(function ($order) {
            $order->items = $this->detailService->orderDetailById($order->order_id);
            return $order;
        });

        return response()->json([
            'message' => 'Order masters fetched successfully!',
            'status' => 200,
            'data' => array_reverse($ordersWithItems->toArray()),
        ]);
    }

    public function getMaxId()
    {
        $max = OrderMaster::max('order_id') ?? 0;
        // $order_no = 'ODP-' . str_pad((OrderMaster::max('order_id') + 1), 5, '0', STR_PAD_LEFT);
        return response()->json(['max_id' => $max]);
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
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $now = now();
        $month = $now->format('m');
        $year = $now->format('y');

        $exchange_rate = ExchangeRate::find($proId);
        $orderCount = OrderMaster::where('created_by', $uid)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $now->format('Y'))
            ->count();
        $order_no = 'ORD' . $uid . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
        $order_date = $now->format('Y-m-d');

        $validated = $request->validate([
            'online' => 'nullable|integer',
            'order_tel' => 'nullable|string|max:255',
            'order_address' => 'nullable|string|max:255',
            'order_payment_status' => 'nullable|string|max:255',
            'order_date' => 'nullable|date|max:255',
            'status' => 'required|integer',
            'order_customer_id' => 'nullable|integer',
            'deliver_id' => 'nullable|integer',
            'through' => 'nullable|integer',
            'term' => 'nullable|integer|max:255',
            'created_by' => 'nullable|integer',
            'reference_no' => 'nullable|string|max:255',
            'sale_type' => 'nullable|string|max:255',
            'delivery_fee' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.item_for' => 'nullable|string|in:sale,sample,free',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payments'           => 'nullable||array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date',
            'payments.*.payment_method' => 'nullable|string',
            'payments.*.transection_id' => 'nullable|string',
            'payments.*.remark' => 'nullable|string'
        ]);
        $order_date =  $order_date?? $now->format('Y-m-d');
        // dd($validated);
        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['item_price'] * (int) $item['quantity'];
        });
        $discountAmount = collect($validated['items'])->sum(function ($item) {
            $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            return round($lineSubtotal * $discountPercent / 100, 2);
        });
        $taxAmount = (float) ($validated['order_tax'] ?? 0);
        $deliveryFee = (float) $validated['delivery_fee'];
        $grandTotal = round($subTotal - $discountAmount + $taxAmount + $deliveryFee, 2);
        $payments = $validated['payments'] ?? 0;
        $payment = $payments? collect($payments)->sum('amount'): 0 ;
        $balance = round($grandTotal - $payment, 2);

        $online = $validated['online'] ?? 0;
        $created_by = $validated['created_by'] ?? $uid;

        $due_date = null;
        if($validated['term']) {
            $due_date =  $order_date ? Carbon::parse( $order_date)->addDays((int)$validated['term']) : Carbon::now()->addDays((int)$validated['term']);
        }
        // Create the order master
        $order_masters = OrderMaster::create([
            'order_no' => $order_no,
            'order_customer_id' => $validated['order_customer_id'] ?? 1,
            'sale_type' => $validated['sale_type'] ?? null,
            'online' => $validated['online'] ?? 0,
            'status' => $validated['delivery_fee'] > 0 ? 1 : $validated['status'],
            'order_tel' => $validated['order_tel']??null,
            'reference_no' => $validated['reference_no'] ?? null,
            'due_date' => $due_date,
            'deliver_id' => $validated['deliver_id'] ?? 1,
            'order_address' => $validated['order_address']??null,
            'order_date' => $order_date,
            'delivery_fee' => $validated['delivery_fee'] ?? 0,
            'through' => $validated['through'] ?? $uid,
            'order_payment_status' => $balance <= 0 ? 'paid' : $validated['order_payment_status'],
            'balance' => $balance,
            'term' => $validated['term'] ?? 0,
            'payment' => $payment,
            'order_subtotal' => $subTotal,
            'order_discount' => $discountAmount,
            'order_tax' => $taxAmount,
            'order_total' => $grandTotal,
            'exchange_rate' => (double)$exchange_rate->usd_to_khr,
            'created_by' => $created_by,
        ]);

        if(!empty($validated['payments'])){
            foreach($validated['payments'] as $payment){
                $paymented = Payment::create([
                    'payment_method'=>$payment['payment_method'],
                    'transection_id'=> $payment['payment_method']!='cash'?$payment['transection_id']??null:null,
                    'amount'=> $payment['amount'],
                    'remark' => $payment['remark']??null,
                    'paid_at' => $payment['paid_at']??now(),
                    'created_by'=> $uid
                ]);
                if(!empty($paymented)){
                    OrderPayment::create([
                        'order_id'=> $order_masters->order_id,
                        'payment_id'=> $paymented->payment_id,
                    ]);
                }
            }
        }

        $order_id = $order_masters->order_id;
        $order_items = [];
        // $order_details = [];

        foreach ($validated['items'] as $item) {
            $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
            $lineTotal = round($lineSubtotal - $lineDiscount, 2);

            $order_items[] = OrderItems::create([
                'order_id' => $order_id,
                'item_id' => $item['item_id'],
                'item_for' => $item['item_for'] ?? 'sale',
                'item_price' => $item['item_price'],
                'discount' => $discountPercent,
                'price' => $lineTotal,
                'quantity' => $item['quantity'],
                ]);
                }
        $profile = DB::table('profiles')->where('id', $proId)->first();
        if($validated['sale_type'] == 'wholesale') {

            $message = $this->formatMessage($order_masters, $validated, $user);
            $init_keyboard = [
                [
                    [
                        'text' => '🧾Invoice',
                        'url'  => 'https://www.chomnenhapp.com/invoice/' . $order_id
                    ]
                ]
            ];
            TelegramService::sendMessage($message, $proId, $init_keyboard, $profile->chat_id);
        }


        if ($online == 1) {
            $message = $this->formatMessage($order_masters, $validated, $user);
            $phone = $validated['order_tel'];
            $profile_id = null;
            if($validated['through']){

                $profile_id = Users::where('id', $validated['through'])->value('profile_id');
            }else{
                $profile_id = $user->profile_id;
            }
            // Broadcast to Pusher
            broadcast(new PrivateChannelEvent("New order by " . $phone, (int)$profile_id))->toOthers();
            $init_keyboard = [
                [
                    [
                        'text' => '🌐View Order',
                        'url'  => 'http://www.chomnenhapp.com/dashboard/order-tracking'
                    ],
                    [
                        'text' => '🧾Invoice',
                        'url'  => 'http://www.chomnenhapp.com/receipt/' . $order_id
                    ]
                ]
            ];
            TelegramService::sendMessage($message, $profile_id, $init_keyboard, $profile->chat_id);
        }
        // return $message;
        // return $this->show($order_masters->order_id);
        return response()->json([
            'message' => 'order master created successfully!',
            'status' => 200,
            "data" => $order_masters,
        ]);
    }


    public function storeWholesale(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $now = now();
        $month = $now->format('m');
        $year = $now->format('y');

        $exchange_rate = ExchangeRate::find($proId);
        $orderCount = OrderMaster::where('created_by', $uid)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $now->format('Y'))
            ->count();
        $order_no = 'ORD' . $uid . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
        $order_date = $now->format('Y-m-d');

        $validated = $request->validate([
            'order_payment_status' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'order_date' => 'required|date|max:255',
            'order_customer_id' => 'required|integer',
            'term' => 'nullable|integer|max:255',
            'seller' => 'nullable|integer',
            'reference_no' => 'nullable|string|max:255',
            'delivery_fee' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.item_for' => 'required|string|in:sale,sample,free',
            'items.*.discount' => 'nullable|numeric|min:0',
            // 'payments'           => 'nullable||array',
            // 'payments.*.amount'  => 'numeric|min:0',
            // 'payments.*.paid_at' => 'date',
            // 'payments.*.payment_method' => 'nullable|string',
            // 'payments.*.transection_id' => 'nullable|string',
            // 'payments.*.remark' => 'nullable|string'
        ]);
        $order_date =  $order_date?? $now->format('Y-m-d');
        // dd($validated);
        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['item_price'] * (int) $item['quantity'];
        });
        $discountAmount = collect($validated['items'])->sum(function ($item) {
            $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            return round($lineSubtotal * $discountPercent / 100, 2);
        });
        $taxAmount = (float) ($validated['order_tax'] ?? 0);
        $deliveryFee = (float) $validated['delivery_fee'];
        $grandTotal = round($subTotal - $discountAmount + $taxAmount + $deliveryFee, 2);
        $payments = $validated['payments'] ?? 0;
        $payment = $payments? collect($payments)->sum('amount'): 0 ;
        $balance = round($grandTotal - $payment, 2);

        $online = $validated['online'] ?? 0;
        $seller = $validated['seller'] ?? $uid;

        $due_date = null;
        if($validated['term']) {
            $due_date =  $order_date ? Carbon::parse( $order_date)->addDays((int)$validated['term']) : Carbon::now()->addDays((int)$validated['term']);
        }
        try{
            DB::beginTransaction();
            // Create the order master
            $order_masters = OrderMaster::create([
                'order_no' => $order_no,
                'order_customer_id' => $validated['order_customer_id'] ?? 1,
                'sale_type' => 'wholesale',
                'online' => 0,
                'status' => 1,
                'reference_no' => $validated['reference_no'] ?? null,
                'due_date' => $due_date,
                'deliver_id' => $validated['deliver_id'] ?? 1,
                'description' => $validated['description'] ,
                'order_date' => $order_date,
                'delivery_fee' => $validated['delivery_fee'] ?? 0,
                'through' => $uid,
                'order_payment_status' => $balance <= 0 ? 'paid' : $validated['order_payment_status'],
                'balance' => $balance,
                'term' => $validated['term'] ?? 0,
                'payment' => $payment,
                'order_subtotal' => $subTotal,
                'order_discount' => $discountAmount,
                'order_tax' => $taxAmount,
                'order_total' => $grandTotal,
                'exchange_rate' => (double)$exchange_rate->usd_to_khr,
                'seller' => $seller,
                'created_by' => $uid,
                'updated_by' => $uid,
            ]);

            // if(!empty($validated['payments'])){
            //     foreach($validated['payments'] as $payment){
            //         $paymented = Payment::create([
            //             'payment_method'=>$payment['payment_method'],
            //             'transection_id'=> $payment['payment_method']!='cash'?$payment['transection_id']??null:null,
            //             'amount'=> $payment['amount'],
            //             'remark' => $payment['remark']??null,
            //             'paid_at' => $payment['paid_at']??now(),
            //             'created_by'=> $uid
            //         ]);
            //         if(!empty($paymented)){
            //             OrderPayment::create([
            //                 'order_id'=> $order_masters->order_id,
            //                 'payment_id'=> $paymented->payment_id,
            //             ]);
            //         }
            //     }
            // }

            $order_id = $order_masters->order_id;
            $order_items = [];
            // $order_details = [];

            foreach ($validated['items'] as $item) {
                $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
                $discountPercent = (float) ($item['discount'] ?? 0);
                $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                $order_items[] = OrderItems::create([
                    'order_id' => $order_id,
                    'item_id' => $item['item_id'],
                    'item_for' => $item['item_for'] ?? 'sale',
                    'item_price' => $item['item_price'],
                    'discount' => $discountPercent,
                    'price' => $lineTotal,
                    'quantity' => $item['quantity'],
                    ]);
                    }
            $profile = DB::table('profiles')->where('id', $proId)->first();

                $message = $this->formatMessage($order_masters, $validated, $user);
                $init_keyboard = [
                    [
                        [
                            'text' => '🧾Invoice',
                            'url'  => 'https://www.chomnenhapp.com/invoice/' . $order_id
                        ]
                    ]
                ];
                TelegramService::sendMessage($message, $proId, $init_keyboard, $profile->chat_id);

                DB::commit();
            // return $message;
            // return $this->show($order_masters->order_id);
            return response()->json([
                'message' => 'order master created successfully!',
                'status' => 200,
                "data" => $order_masters->order_id,
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error create wholesale: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    public function storeRetail(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $now = now();
        $month = $now->format('m');
        $year = $now->format('y');

        $exchange_rate = ExchangeRate::find($proId);
        $orderCount = OrderMaster::where('created_by', $uid)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $now->format('Y'))
            ->count();
        $order_no = 'ORD' . $uid . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
        $order_date = $now->format('Y-m-d');

        $validated = $request->validate([
            'order_date' => 'required|date',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.discount' => 'required|integer|numeric|min:0|max:100',
            'amount'  => 'numeric|min:0',
            'paid_at' => 'date',
            'payment_method' => 'required|string',
            'transection_id' => 'nullable|string',
            'remark' => 'nullable|string'
        ]);
        $order_date =  $order_date?? $now->format('Y-m-d');
        // dd($validated);
        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['item_price'] * (int) $item['quantity'];
        });
        $discountAmount = collect($validated['items'])->sum(function ($item) {
            $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            return round($lineSubtotal * $discountPercent / 100, 2);
        });
        $grandTotal = round($subTotal - $discountAmount, 2);
        $created_by =  $uid;

        try{
            DB::beginTransaction();
            // Create the order master
            $order_masters = OrderMaster::create([
                'order_no' => $order_no,
                'order_customer_id' => 1,
                'sale_type' => 'wholesale',
                'online' => 0,
                'status' => 1,
                'reference_no' => null,
                'due_date' => $order_date,
                'deliver_id' => 1,
                'order_date' => $order_date,
                'delivery_fee' => 0,
                'through' => $uid,
                'order_payment_status' => 'paid',
                'balance' => 0,
                'term' => 0,
                'payment' => $grandTotal,
                'order_subtotal' => $subTotal,
                'order_discount' => $discountAmount,
                'order_tax' => 0,
                'order_total' => $grandTotal,
                'exchange_rate' => (double)$exchange_rate->usd_to_khr,
                'created_by' => $created_by,
            ]);


            $paymented = Payment::create([
                'payment_method'=> $validated['payment_method'],
                'transection_id'=> $validated['payment_method']!='cash'?$validated['transection_id']??null:null,
                'amount'=> $grandTotal,
                'remark' => null,
                'paid_at' => now(),
                'created_by'=> $uid
            ]);
            if(!empty($paymented)){
                OrderPayment::create([
                    'order_id'=> $order_masters->order_id,
                    'payment_id'=> $paymented->payment_id,
                ]);
            }



            $order_id = $order_masters->order_id;
            $order_items = [];
            // $order_details = [];

            foreach ($validated['items'] as $item) {
                $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
                $discountPercent = (float) ($item['discount'] ?? 0);
                $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                $order_items[] = OrderItems::create([
                    'order_id' => $order_id,
                    'item_id' => $item['item_id'],
                    'item_for' => $item['item_for'] ?? 'sale',
                    'item_price' => $item['item_price'],
                    'discount' => $discountPercent,
                    'price' => $lineTotal,
                    'quantity' => $item['quantity'],
                    ]);
                }
            // $profile = DB::table('profiles')->where('id', $proId)->first();
            // if($validated['sale_type'] == 'wholesale') {

            //     $message = $this->formatMessage($order_masters, $validated, $user);
            //     $init_keyboard = [
            //         [
            //             [
            //                 'text' => '🧾Invoice',
            //                 'url'  => 'https://www.chomnenhapp.com/invoice/' . $order_id
            //             ]
            //         ]
            //     ];
            //     TelegramService::sendMessage($message, $proId, $init_keyboard, $profile->chat_id);
            // }
            // return $message;
            // return $this->show($order_masters->order_id);
            DB::commit();
            return response()->json([
                'message' => 'order master created successfully!',
                'status' => 200,
                "data" => $order_masters->order_id,
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error create retail: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    function formatMessage($order, $validated, $user) {
            $order_no = $order->order_no;
            $sale_type = $order->sale_type;
            $order_date = $order->created_at->format('Y-m-d');
            $grandTotal = number_format($order->order_total, 2);
            $customer = Customers::find($validated['order_customer_id']);
            $profile_id = null;
            $through = $validated['through'] ?? null;
            if($through){

                $profile_id = Users::where('id', $validated['through'])->value('profile_id');
            }else{
                $profile_id = $user->profile_id;
            }
            $profile = DB::table('profiles')->where('id', $profile_id)->first();

            $phone = $validated['order_tel'] ?? ($customer ? $customer->customer_tel : 'N/A');
            $address = $validated['order_address'] ?? ($customer ? $customer->customer_address : 'N/A');

            // Build items list dynamically
            $itemsList = "";
            foreach ($validated['items'] as $item) {
                $itemData = Items::where('item_id', $item['item_id'])->first();
                $lineTotal = (float)$item['item_price'] * (int)$item['quantity'];
                $itemsList .= "• <b>{$itemData->item_name}</b> | x{$item['quantity']} | <b>\${$lineTotal}</b>\n";
            }

            $message = "🛒 <b>New Order Received</b>\n\n" .
                "🏪 <b>Shop:</b> " . ($profile->profile_name ?? 'N/A') . "\n" .
                "🆔 <b>Order No:</b> <code>{$order_no}</code>\n\n" .
                "📞 <b>Buyer:</b> " . ($customer->customer_tel ?? $user->phone_number ?? 'N/A') . "\n" .
                "📦 <b>Recipient:</b> {$phone}\n\n" .
                "📌 <b>Address:</b> {$address}\n";

            if ($customer) {
                if ($customer->customer_villages) $message .= "🏡 <b>Village:</b> {$customer->customer_villages}\n";
                if ($customer->customer_communes) $message .= "🗺️ <b>Commune:</b> {$customer->customer_communes}\n";
                if ($customer->customer_districts) $message .= "🏙️ <b>District:</b> {$customer->customer_districts}\n";
                if ($customer->customer_provinces) $message .= "🌆 <b>Province:</b> {$customer->customer_provinces}\n";
            }

            if($sale_type != 'sale') {

                $due_date = $order->due_date ? $order->due_date : 'N/A';
                $total_paid = number_format($order->payment, 2);
                $payment_status = $order->order_payment_status ? ucfirst($order->order_payment_status) : 'N/A';
                $payment_method = $order->order_payment_method ? ucfirst($order->order_payment_method) : 'N/A';
                $message = "📢 <b>New Wholesale Order Received</b>\n\n" .
                    "📅 <b>Due Date:</b> {$due_date}\n" .
                    "💰 <b>Total Paid:</b> 💵 <b>\${$total_paid}</b>\n" .
                    "💳 <b>Payment Status:</b> {$payment_status}\n" .
                    "💳 <b>Payment Method:</b> {$payment_method}";
            }

            $message .= "\n📅 <b>Date:</b> {$order_date}\n\n" .
                "📦 <b>Items List:</b>\n{$itemsList}\n" .
                "💰 <b>Total:</b> 💵 <b>\${$grandTotal}</b>";

            return $message;
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // $user = Auth::user();
        // $uid = $user->id;
        $orderMasters = DB::table('order_masters as om')
        ->join('customers as cu','om.order_customer_id','=',"customer_id")
        ->join('users as u', 'u.id', '=', 'om.created_by')
        ->join('delivers as d', 'd.deliver_id', '=', 'om.deliver_id')
        ->where('order_id', $id)
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->select('cu.customer_name', 'u.username as created_by_name', 'd.deliver_name','cu.customer_email',"om.*")->get();
        if ($orderMasters->isEmpty()) {
            return response()->json([
                'message' => 'Order masters get fail!',
                'status' => 404,
            ]);
        }
        // Attach items to each order
        $ordersWithItems = $orderMasters->map(function ($order) {
            $order->items = $this->detailService->orderDetailById($order->order_id);
            $order->payments = $this->detailService->orderPayment($order->order_id);
            // Show new fields
            $order->order_customer_id = $order->order_customer_id ?? null;
            $order->sale_type = $order->sale_type ?? null;
            return $order;
        });
        return response()->json([
            'message' => 'Order masters fetched successfully!',
            'status' => 200,
            'data' => $ordersWithItems[0],
        ]);
    }


    public function update(Request $request, string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $order_masters = OrderMaster::find($id);
        if (!$order_masters) {
            return response()->json([
                'message' => 'order master not found!',
                'status' => 404,
            ]);
        }
        $exchange_rate = ExchangeRate::find($proId);
        $order_date = now()->format('Y-m-d');
        $validated = $request->validate([
            'order_tel' => 'nullable|string|max:255',
            'order_address' => 'nullable|string|max:255',
            'order_date' => 'date',
            'transection_id' => 'nullable|string|max:255',
            'remark' => 'nullable|string|max:255',
            'order_payment_status' => 'nullable|string|max:255',
            'order_payment_method' => 'nullable|string|max:255',
            'deliver_id' => 'nullable|integer',
            'created_by' => 'nullable|integer',
            'delivery_fee' => 'numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payment' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'term' => 'nullable|integer|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_for' => 'nullable|string|in:sale,sample,free',
            'items.*.discount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.unit_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'payments'           => 'array',
            'payments.*.amount'  => 'numeric|min:0',
            'payments.*.paid_at' => 'date',
            'payments.*.payment_method' => 'nullable|string',
            'payments.*.transection_id' => 'nullable|string',
            'payments.*.remark' => 'nullable|string'
        ]);

        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['unit_price'] * (int) $item['quantity'];
        });
        $discountAmount = collect($validated['items'])->sum(function ($item) {
            $lineSubtotal = (float) $item['unit_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            return round($lineSubtotal * $discountPercent / 100, 2);
        });
        $taxAmount = (float) ($validated['order_tax'] ?? 0);
        $deliveryFee = (float) $validated['delivery_fee'];
        $grandTotal = round($subTotal - $discountAmount + $taxAmount + $deliveryFee, 2);
        $payment = (float) $validated['payment'];
        $balance = round($grandTotal - $payment, 2);

        $created_by = $validated['created_by'] ?? $uid;
        $due_date = null;
        $term = $validated['term'] ?? 0;
        if($term) {
            $due_date = $validated['order_date'] ? Carbon::parse($validated['order_date'])->addDays((int)$validated['term']) : Carbon::now()->addDays((int)$validated['term']);
        }
        // Create the order master
        $order_masters->update([
            'order_tel' => $validated['order_tel'],
            'order_address' => $validated['order_address'],
            'deliver_id' => $validated['deliver_id']??1,
            'order_date' => $validated['order_date'],
            'created_by' => $created_by,
            'delivery_fee' => $validated['delivery_fee'],
            'order_payment_status' => $balance <= 0 ? 'paid' : $validated['order_payment_status'],
            'balance' => $balance,
            'order_subtotal' => $subTotal,
            'order_discount' => $discountAmount,
            'order_tax' => $taxAmount,
            'order_total' => $grandTotal,
            'due_date' => $due_date,
            'term' => $validated['term'] ?? 0,
        ]);

        $paymentIds = OrderPayment::where('order_id', $id)->pluck('payment_id');
        $paymented = null;
        if (!empty($validated['payments'])) {
            foreach ($validated['payments'] as $payment) {
                $amount = $payment['amount']??0;
                $payment_method = $payment['payment_method']??null;
                if($amount > 0){
                    $existingPayment = count($paymentIds) > 0 ? Payment::where('payment_id', $paymentIds[count($paymentIds)-1])->first() : null;
                    if($existingPayment){
                        $paymented = $existingPayment->update([
                            'payment_method'=>$payment_method??'cash',
                            'transection_id'=> $payment_method!='cash'?$payment['transection_id']??null:null,
                            'amount'=> (float)$amount,
                            'remark' => $payment['remark']??null,
                            'paid_at' => $payment['paid_at']??now(),
                            'created_by'=> $uid
                        ]);
                    }else{
                        $paymented = Payment::create([
                            'payment_method'=>$payment_method??'cash',
                            'transection_id'=> $payment_method!='cash'?$payment['transection_id']??null:null,
                            'amount'=> (float)$amount,
                            'remark' => $payment['remark']??null,
                            'paid_at' => $payment['paid_at']??now(),
                            'created_by'=> $uid
                        ]);
                        if(!empty($paymented)){
                            OrderPayment::create([
                                'order_id'=> $id,
                                'payment_id'=> $paymented->payment_id,
                            ]);
                        }
                    }
                }
            }
        }

        if($paymented){
            $total_paid = count($paymentIds) > 0 ? Payment::whereIn('payment_id', $paymentIds)->select(DB::raw('SUM(amount) as total_payment'))->first()->total_payment : $paymented->amount;
            $order_masters->update([
                'payment' => $total_paid,
                'balance' => (float)$order_masters->order_total - (float)$total_paid,
            ]);
        }

        $order_items = [];
        if ($order_masters) {
            OrderItems::where('order_id', $id)->delete();
        }
        foreach ($validated['items'] as $item) {
            $lineSubtotal = (float) $item['unit_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
            $lineTotal = round($lineSubtotal - $lineDiscount, 2);

            // return response()->json([
            //     'discount' => $item['discount'],
            // ]);
            $order_items[] = OrderItems::create([
                'order_id' => $order_masters->order_id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'item_price' => $item['unit_price'],
                'discount' => (float)$item['discount'],
                'price' => $lineTotal,
                'quantity' => $item['quantity'],
            ]);
        }

        // return $this->show($id);
        return response()->json([
            'message' => 'order master updated successfully!',
            'status' => 200,
        ]);
    }


    public function updateWholesale(Request $request, string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $order_masters = OrderMaster::find($id);
        if (!$order_masters) {
            return response()->json([
                'message' => 'order master not found!',
                'status' => 404,
            ]);
        }

        $validated = $request->validate([
            'order_payment_status' => 'required|string|max:255',
            'description' => 'required|string|max:255',
            'order_date' => 'required|date',
            'order_customer_id' => 'required|integer',
            'term' => 'nullable|integer',
            'seller' => 'nullable|integer',
            'reference_no' => 'nullable|string|max:255',
            'delivery_fee' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.item_for' => 'required|string|in:sale,sample,free',
            'items.*.discount' => 'nullable|numeric|min:0',
            // 'payments'           => 'nullable||array',
            // 'payments.*.amount'  => 'numeric|min:0',
            // 'payments.*.paid_at' => 'date',
            // 'payments.*.payment_method' => 'nullable|string',
            // 'payments.*.transection_id' => 'nullable|string',
            // 'payments.*.remark' => 'nullable|string'
        ]);

        $subTotal = collect($validated['items'])->sum(function ($item) {
            return (float) $item['item_price'] * (int) $item['quantity'];
        });
        $discountAmount = collect($validated['items'])->sum(function ($item) {
            $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            return round($lineSubtotal * $discountPercent / 100, 2);
        });
        $taxAmount = (float) ($validated['order_tax'] ?? 0);
        $deliveryFee = (float) $validated['delivery_fee'];
        $grandTotal = round($subTotal - $discountAmount + $taxAmount + $deliveryFee, 2);

        $seller = $validated['seller'] ?? $uid;
        $due_date = null;
        $term = $validated['term'] ?? 0;
        if($term) {
            $due_date = $validated['order_date'] ? Carbon::parse($validated['order_date'])->addDays((int)$validated['term']) : Carbon::now()->addDays((int)$validated['term']);
        }
        try{
            DB::beginTransaction();
            // Create the order master
            $order_masters->update([
                'deliver_id' => 1,
                'order_date' => $validated['order_date'],
                'description' => $validated['description'],
                'seller' => $seller,
                'updated_by' => $uid,
                'delivery_fee' => $validated['delivery_fee'],
                'order_payment_status' => $validated['order_payment_status'],
                'balance' => (float)$grandTotal - (float)$order_masters->paymented,
                'order_subtotal' => $subTotal,
                'order_discount' => $discountAmount,
                'order_tax' => $taxAmount,
                'order_total' => $grandTotal,
                'due_date' => $due_date,
                'term' => $term ?? 0,
            ]);

            // $paymentIds = OrderPayment::where('order_id', $id)->pluck('payment_id');
            // $paymented = null;
            // if (!empty($validated['payments'])) {
            //     foreach ($validated['payments'] as $payment) {
            //         $amount = $payment['amount']??0;
            //         $payment_method = $payment['payment_method']??null;
            //         if($amount > 0){
            //             $existingPayment = count($paymentIds) > 0 ? Payment::where('payment_id', $paymentIds[count($paymentIds)-1])->first() : null;
            //             if($existingPayment){
            //                 $paymented = $existingPayment->update([
            //                     'payment_method'=>$payment_method??'cash',
            //                     'transection_id'=> $payment_method!='cash'?$payment['transection_id']??null:null,
            //                     'amount'=> (float)$amount,
            //                     'remark' => $payment['remark']??null,
            //                     'paid_at' => $payment['paid_at']??now(),
            //                     'created_by'=> $uid
            //                 ]);
            //             }else{
            //                 $paymented = Payment::create([
            //                     'payment_method'=>$payment_method??'cash',
            //                     'transection_id'=> $payment_method!='cash'?$payment['transection_id']??null:null,
            //                     'amount'=> (float)$amount,
            //                     'remark' => $payment['remark']??null,
            //                     'paid_at' => $payment['paid_at']??now(),
            //                     'created_by'=> $uid
            //                 ]);
            //                 if(!empty($paymented)){
            //                     OrderPayment::create([
            //                         'order_id'=> $id,
            //                         'payment_id'=> $paymented->payment_id,
            //                     ]);
            //                 }
            //             }
            //         }
            //     }
            // }

            // if($paymented){
            //     $total_paid = count($paymentIds) > 0 ? Payment::whereIn('payment_id', $paymentIds)->select(DB::raw('SUM(amount) as total_payment'))->first()->total_payment : $paymented->amount;
            //     $order_masters->update([
            //         'payment' => $total_paid,
            //         'balance' => (float)$order_masters->order_total - (float)$total_paid,
            //     ]);
            // }

            $order_items = [];
            if ($order_masters) {
                OrderItems::where('order_id', $id)->delete();
            }
            foreach ($validated['items'] as $item) {
                $lineSubtotal = (float) $item['item_price'] * (int) $item['quantity'];
                $discountPercent = (float) ($item['discount'] ?? 0);
                $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
                $lineTotal = round($lineSubtotal - $lineDiscount, 2);

                // return response()->json([
                //     'discount' => $item['discount'],
                // ]);
                $order_items[] = OrderItems::create([
                    'order_id' => $order_masters->order_id,
                    'item_id' => $item['item_id'],
                    'item_price' => $item['item_price'],
                    'discount' => (float)$item['discount'],
                    'price' => $lineTotal,
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();
            // return $this->show($id);
            return response()->json([
                'message' => 'order master updated successfully!',
                'status' => 200,
            ]);
        }catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error update wholesale: ' . $e->getMessage(),
                'status' => 500
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order_master = OrderMaster::find($id);
        if (!$order_master) {
            return response()->json([
                'message' => 'order master not found!',
                'status' => 404,
            ]);
        }
        $order_master->is_deleted = 1;
        $order_master->save();
        $order_items = OrderItems::where('order_id', $id)->get();
        if (!$order_items->isEmpty()) {
            foreach ($order_items as $item) {
                $item->update([
                    'is_deleted' => 1,
                ]);
            }
        }
        return response()->json([
            'message' => 'order master deleted successfully!',
            'status' => 200,
        ]);
    }

    public function cancel(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $status_code = [
            1 => 'pending',
            2 => 'editing',
            3 => 'packaged',
            4 => 'pickup',
            5 => 'delivering',
            6 => 'completed',
        ];

        $track = OrderTracking::create([
            'order_id'=> $id,
            'status' =>$status_code[7],
            'created_by' => $uid
        ]);
        if(!empty($track)){
            $orders->status = 7;
            $orders->save();
        }

        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
        ]);
    }

    public function uncancel(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $status_code = [
            1 => 'pending',
            2 => 'editing',
            3 => 'packaged',
            4 => 'pickup',
            5 => 'delivering',
            6 => 'completed',
        ];

        $track = OrderTracking::create([
            'order_id'=> $id,
            'status' =>$status_code[1],
            'created_by' => $uid
        ]);
        if(!empty($track)){
            $orders->status = 1;
            $orders->save();
        }


        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
        ]);
    }
    public function receiveOrder(string $id)
    {
        $orders = OrderMaster::where('order_id', $id);
        $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'status' => 5,
        ]);
        if (!$orderItems->isEmpty()) {
            foreach ($orderItems as $item) {
                $item->update([
                    'status' => 5,
                ]);
            }
        }
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
            'data' => $orders->first()
        ]);
    }



    public function orderTransection(Request $request)
{
    $user  = Auth::user();
    $proId = $user->profile_id;

    $limit  = $request->input('limit', 10);
    $page   = $request->input('page', 1);
    $search = $request->input('search'); // 🔍 search keyword

    $orders = DB::table('order_items as oi')
        ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
        ->join('items as i', 'oi.item_id', '=', 'i.item_id')
        ->join('categories as c', 'c.category_id', '=', 'i.category_id')
        ->join('brands as b', 'b.brand_id', '=', 'i.brand_id')
        ->join('users as u', 'om.created_by', '=', 'u.id')
        ->join('profiles as p', 'u.profile_id', '=', 'p.id')
        ->select(
            'oi.item_id',
            'i.item_name',
            'i.barcode',
            'i.item_code',
            'c.category_name',
            'b.brand_name',
            DB::raw('0 AS image'),
            DB::raw('0 AS images'),
            DB::raw('0 AS attributes'),
            DB::raw('SUM(oi.quantity * oi.item_price) AS amount_sold'),
            DB::raw('SUM(oi.quantity) AS total_quantity_sold')
        )
        ->where('om.is_deleted', 0)
        ->where('p.id', $proId)

        // 🔍 SEARCH FILTER
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('i.item_name', 'like', "%{$search}%")
                  ->orWhere('i.item_code', 'like', "%{$search}%")
                  ->orWhere('i.barcode', 'like', "%{$search}%")
                  ->orWhere('c.category_name', 'like', "%{$search}%")
                  ->orWhere('b.brand_name', 'like', "%{$search}%");
            });
        })

        ->groupBy(
            'i.item_id',
            'i.item_name',
            'i.barcode',
            'i.item_code',
            'c.category_name',
            'b.brand_name'
        )
        ->orderByDesc('amount_sold')
        ->paginate($limit, ['*'], 'page', $page);

    if ($orders->isEmpty()) {
        return response()->json([
            'message' => 'Order transaction not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    // Enrich ONLY current page items
    foreach ($orders as $order) {
        $images = $this->itemService->getImage($order->item_id);
        $attrs  = $this->attributeService->transformAttributes($order->item_id);

        $order->attributes = $attrs ?? null;
        $order->image      = $images[0] ?? null;
        $order->images     = $images ?? [];
    }

    return response()->json([
        'message' => 'Order transaction fetched successfully!',
        'status'  => 200,
        'data'    => $orders->items(),
        'pagination' => [
            'current_page' => $orders->currentPage(),
            'per_page'     => $orders->perPage(),
            'total'        => $orders->total(),
            'last_page'    => $orders->lastPage(),
        ]
    ]);
}




    public function statusOrder($id, $status){
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }

        if((int)$order->status >= (int)$status ){
            return response()->json([
                'message'=>'Cannot change to back status',
                'status' => 304,
            ],304);
        }

        if($order->status == 6){
            return response()->json([
                'message' => 'Cannot change because it completed',
                'status' => 304,
            ],304);
        }

        if ((int)$status === 6) {
            $orderItems = OrderItems::where('order_id', $id)
                ->where('is_deleted', 0)
                ->get();

            $outOfStockItems = [];
            foreach ($orderItems as $item) {
                $inStock = (double)($this->detailService->quanItems($item->item_id)[0]->in_stock ?? 0);
                $requiredQty = (double)($item->quantity ?? 0);

                if ($requiredQty > $inStock) {
                    $outOfStockItems[] = [
                        'item_id' => $item->item_id,
                        'item_name' => $item->item_name,
                        'required_quantity' => $requiredQty,
                        'available_quantity' => $inStock,
                    ];
                }
            }

            if (!empty($outOfStockItems)) {
                return response()->json([
                    'message' => 'Stock is not enough for some items',
                    'status' => 422,
                    'out_of_stock_items' => $outOfStockItems,
                ], 422);
            }
        }
        $status_code = [
            1 => 'pending',
            2 => 'editing',
            3 => 'packaged',
            4 => 'pickup',
            5 => 'delivering',
            6 => 'completed',
        ];

        if($status){
            $track = OrderTracking::create([
                'order_id'=> $id,
                'status' =>$status_code[$status],
                'created_by' => $uid
            ]);
            if(!empty($track)){
                $order->status = $status;
                $order->save();
            }
        }

        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'Order updated status successfully',
            'status' => 200,
        ]);
    }

    public function addDeliver($id,$deliver_id){
        $user = Auth::user();
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }

        $order->deliver_id = $deliver_id;
        $order->save();
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'update delivery sevice!',
            'status' => 200,
        ]);
    }


    public function addDeliveryFee($id,$delivery_fee){
        $user = Auth::user();
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }

        $order->delivery_fee = $delivery_fee;
        $order->order_total = $order->order_total + $delivery_fee;
        $order->save();
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'update delivery fee!',
            'status' => 200,
        ]);
    }

    public function addPayment(Request $request, $id ,$payment){
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        $validated = $request->validate([
            'payment_method' => 'nullable|string',
            'transection_id' => 'nullable|string',
            'amount'=> 'nullable|numeric',
            'paid_at' => 'nullable|date',
        ]);

        $amount = $validated['amount']??(int)$payment??0;
        if((float)$amount <= 0){
            return response()->json([
                'message' => 'Please add payment amount',
                'status' => 404,
            ],304);
        }
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ],404);
        }

        if($order->order_payment_status == 'paid'){
            return response()->json([
                'message' => 'Order already paid!',
                'status' => 422,
            ],422);
        }

        if($payment > $order->balance){
            return response()->json([
                'message' => 'Payment exceeds balance!',
                'status' => 422,
            ],422);
        }

        $payment_method = $validated['payment_method']??'cash';
        if((float)$amount > 0){
            $paymented = Payment::create([
                'payment_method'=>$payment_method??'cash',
                'transection_id'=> $payment_method!='cash'?$validated['transection_id']??null:null,
                'amount'=> (float)$amount,
                'remark' => $validated['remark']??null,
                'paid_at' => $validated['paid_at']??now(),
                'created_by'=> $uid
            ]);
            if(!empty($paymented)){
                OrderPayment::create([
                    'order_id'=> $id,
                    'payment_id'=> $paymented->payment_id,
                ]);
            }
        }

        $order->balance = $order->balance - (float)$amount;
        $order->payment = $order->payment + (float)$amount;

        $order->save();
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'update payment!',
            'status' => 200,
        ]);
    }


    public function topThereUserOrder(Request $request){
        $user = Auth::user();
        $proId = $user->profile_id;
        $filter = $request->input('filter', 'price'); // 'order_total' or 'quantity'

        $subQuery = DB::table('order_items')
            ->select('order_id', DB::raw('SUM(quantity) as total_qty'))
            ->where('is_deleted', 0)
            ->groupBy('order_id');

        $orders = DB::table('order_masters as om')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->leftJoinSub($subQuery, 'oi', 'om.order_id', '=', 'oi.order_id')
            ->select(
                'om.created_by',
                'u.username',
                DB::raw('SUM(COALESCE(oi.total_qty, 0)) as quantity'),
                DB::raw('SUM(om.order_total) as order_total')
            )
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->where('p.id', $proId)
            ->groupBy('om.created_by', 'u.username')
            ->orderByDesc($filter === 'quantity' ? 'quantity' : 'order_total')
            ->limit(3)
            ->get();

        return response()->json([
            'message' => 'Top 3 users fetched successfully!',
            'status' => 200,
            'data' => $orders,
        ]);
    }
}
