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
use App\Models\OrderAttribute;
use App\Models\Users;
use App\Services\DetailService;
use App\Services\AttributeService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ItemService;
use App\Services\TelegramService;


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
                'oi.item_name',
                'oi.item_id',
                'oi.item_price',
                'oi.quantity',
                'oi.discount',
                'oi.price',
                'i.item_code',
                DB::raw('MIN(im.id) as first_image_id'),
                DB::raw('MIN(im.image) as first_image')
            )
            ->groupBy(
                'oi.id',
                'oi.item_name',
                'oi.item_id',
                'oi.item_price',
                'oi.quantity',
                'oi.discount',
                'oi.price',
                'i.item_code'
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

        return [
            'order_header' => [
                'id' => (int) $order->order_id,
                'online' => (bool) $order->online,
                'through' => null,
                'order_no' => $order->order_no,
                'sale_type' => $order->sale_type,
                'exchange_rate' => $exchangeRate,
                'payment_method' => $order->order_payment_method,
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
                'paymented' => (float) $order->payment,
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
            'online' => 'required|integer',
            'status' => 'required|integer',
            'order_tel' => 'required|string|max:255',
            'order_address' => 'required|string|max:255',
            'order_payment_status' => 'nullable|string|max:255',
            'order_payment_method' => 'nullable|string|max:255',
            'order_customer_id' => 'nullable|integer',
            'deliver_id' => 'nullable|integer',
            'through' => 'nullable|integer',
            'sale_type' => 'nullable|string|max:255',
            'delivery_fee' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payment' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);
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
        $payment = (float) $validated['payment'];
        $balance = round($grandTotal - $payment, 2);

        // Create the order master
        $order_masters = OrderMaster::create([
            'order_no' => $order_no,
            'order_customer_id' => $validated['order_customer_id'] ?? 1,
            'sale_type' => $validated['sale_type'] ?? null,
            'online' => $validated['online'],
            'status' => $validated['delivery_fee'] > 0 ? 1 : $validated['status'],
            'order_tel' => $validated['order_tel'],
            'deliver_id' => $validated['deliver_id'],
            'order_address' => $validated['order_address'],
            'order_date' => $order_date,
            'delivery_fee' => $validated['delivery_fee'] ?? 0,
            'through' => $validated['through'] ?? $uid,
            'order_payment_status' => $balance <= 0 ? 'paid' : $validated['order_payment_status'],
            'order_payment_method' => $validated['order_payment_method'],
            'balance' => $balance,
            'payment' => $payment,
            'order_subtotal' => $subTotal,
            'order_discount' => $discountAmount,
            'order_tax' => $taxAmount,
            'order_total' => $grandTotal,
            'exchange_rate' => (double)$exchange_rate->usd_to_khr,
            'created_by' => $uid,
        ]);

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
                'item_name' => $item['item_name'],
                'item_price' => $item['item_price'],
                'discount' => $discountPercent,
                'price' => $lineTotal,
                'quantity' => $item['quantity'],
            ]);
        }


        if ($validated['online'] == 1) {
            $customer = Customers::find($validated['order_customer_id']);
            $profile_id = Users::where('id', $validated['through'])->value('profile_id');
            $profile = DB::table('profiles')->where('id', $profile_id)->first();

            $phone = $validated['order_tel'] ?? ($customer ? $customer->customer_tel : 'N/A');
            $address = $validated['order_address'] ?? ($customer ? $customer->customer_address : 'N/A');

            // Build items list dynamically
            $itemsList = "";
            foreach ($validated['items'] as $item) {
                $lineTotal = (float)$item['item_price'] * (int)$item['quantity'];
                $itemsList .= "• <b>{$item['item_name']}</b> | x{$item['quantity']} | <b>\${$lineTotal}</b>\n";
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

            $message .= "\n📅 <b>Date:</b> {$order_date}\n\n" .
                "📦 <b>Items List:</b>\n{$itemsList}\n" .
                "💰 <b>Total:</b> 💵 <b>\${$grandTotal}</b>";

            // Broadcast to Pusher
            broadcast(new PrivateChannelEvent("New order by " . $phone, (int)$profile_id))->toOthers();
            TelegramService::sendMessage($message, $profile_id);
        }
        // return $message;
        // return $this->show($order_masters->order_id);
        return response()->json([
            'message' => 'order master created successfully!',
            'status' => 200,
            "data" => $order_masters,
        ]);
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $orderMasters = DB::table('order_masters as om')
        ->join('customers as cu','om.order_customer_id','=',"customer_id")
        ->where('order_id', $id)
            ->where('om.is_deleted', 0)
            ->where('om.is_active', 1)
            ->select('cu.customer_name','cu.customer_email',"om.*")->get();
        if ($orderMasters->isEmpty()) {
            return response()->json([
                'message' => 'Order masters get fail!',
                'status' => 404,
            ]);
        }
        // Attach items to each order
        $ordersWithItems = $orderMasters->map(function ($order) {
            $order->items = $this->detailService->orderDetailById($order->order_id);
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
            'order_tel' => 'required|string|max:255',
            'order_address' => 'required|string|max:255',
            'order_date' => 'date',
            'order_payment_status' => 'nullable|string|max:255',
            'order_payment_method' => 'nullable|string|max:255',
            'deliver_id' => 'nullable|integer',
            'delivery_fee' => 'numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payment' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer|exists:items,item_id',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.discount' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
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

        // Create the order master
        $order_masters->update([
            'order_tel' => $validated['order_tel'],
            'order_address' => $validated['order_address'],
            'deliver_id' => $validated['deliver_id'],
            'order_date' => $validated['order_date'],
            'delivery_fee' => $validated['delivery_fee'],
            'order_payment_status' => $balance <= 0 ? 'paid' : $validated['order_payment_status'],
            'order_payment_method' => $validated['order_payment_method'],
            'balance' => $balance,
            'payment' => $payment,
            'order_subtotal' => $subTotal,
            'order_discount' => $discountAmount,
            'order_tax' => $taxAmount,
            'order_total' => $grandTotal,
        ]);

        $order_items = [];
        if ($order_masters) {
            OrderItems::where('order_id', $id)->delete();
        }
        foreach ($validated['items'] as $item) {
            $lineSubtotal = (float) $item['unit_price'] * (int) $item['quantity'];
            $discountPercent = (float) ($item['discount'] ?? 0);
            $lineDiscount = round($lineSubtotal * $discountPercent / 100, 2);
            $lineTotal = round($lineSubtotal - $lineDiscount, 2);

            $order_items[] = OrderItems::create([
                'order_id' => $order_masters->order_id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'item_price' => $item['unit_price'],
                'discount' => $item['discount'],
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
        $proId = $user->profile_id;
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'status' => 7,
        ]);
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
        ]);
    }

    public function uncancel(string $id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'status' => 1,
        ]);

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




    public function statusOrder($id,$status){
        $user = Auth::user();
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }

        if($order->status == 6){
            return response()->json([
                'message' => 'Cannot change because it completed',
                'status' => 200,
            ]);
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

        if($status == 7){
            $order->status = $status;
            $order->save();
        }else{
            $order->status = $status;
            $order->save();
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

    public function addPayment($id,$payment){
        $user = Auth::user();
        $proId = $user->profile_id;
        $order = OrderMaster::find($id);
        if (empty($order)) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }

        if($order->order_payment_status == 'paid'){
            return response()->json([
                'message' => 'Order already paid!',
                'status' => 422,
            ]);
        }

        if($payment > $order->balance){
            return response()->json([
                'message' => 'Payment exceeds balance!',
                'status' => 422,
            ]);
        }

        $order->payment = $order->payment + $payment;
        $order->balance = $order->balance - $payment;
        if($order->balance <= 0){
            $order->order_payment_status = 'paid';
        }
        $order->save();
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'update payment!',
            'status' => 200,
        ]);
    }
}
