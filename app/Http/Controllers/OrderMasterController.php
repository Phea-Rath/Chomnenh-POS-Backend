<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\OnlineEvent;
use App\Events\OrderMessage;
use App\Events\PrivateChannelEvent;
use App\Models\OrderItems;
use App\Models\OrderMaster;
use App\Models\ExchangeRate;
use App\Models\OrderAttribute;
use App\Services\DetailService;
use App\Services\AttributeService;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ItemService;


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

    public function index()
    {
        $user = auth::user();
        $uid = $user->id;
        $orderMasters = DB::table('order_masters as om')
        ->join('customers as cu','om.order_customer_id','=',"customer_id")
            ->where('om.is_deleted', 0)
            ->where('om.created_by', $uid)
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
        // return response()->json([
        //     "data"=>$exchange_rate->usd_to_khr,
        // ]);
        // Count orders for this profile in the current month/year
        $orderCount = OrderMaster::where('created_by', $uid)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $now->format('Y'))
            ->count();
        $order_no = 'ORD' . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
        $order_date = $now->format('Y-m-d');

        $validated = $request->validate([
            'status' => 'required|integer',
            'order_tel' => 'required|string|max:255',
            'order_address' => 'required|string|max:255',
            'order_payment_status' => 'nullable|string|max:255',
            'order_payment_method' => 'nullable|string|max:255',
            'order_customer_id' => 'nullable|integer',
            'sale_type' => 'nullable|string|max:255',
            'delivery_fee' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_subtotal' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_discount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_total' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'balance' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'payment' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.item_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.discount' => 'required|integer',
            'items.*.price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.item_cost' => 'required|numeric',
            'items.*.item_wholesale_price' => 'required|numeric',
        ]);
        // dd($validated);
        // Create the order master
        $order_masters = OrderMaster::create([
            'order_no' => $order_no,
            'order_customer_id' => $validated['order_customer_id'] ?? null,
            'sale_type' => $validated['sale_type'] ?? null,
            'status' => $validated['status'],
            'order_tel' => $validated['order_tel'],
            'order_address' => $validated['order_address'],
            'order_date' => $order_date,
            'delivery_fee' => $validated['delivery_fee'],
            'order_payment_status' => $validated['order_payment_status'],
            'order_payment_method' => $validated['order_payment_method'],
            'balance' => $validated['balance'],
            'payment' => $validated['payment'],
            'order_subtotal' => $validated['order_subtotal'],
            'order_discount' => $validated['order_discount'],
            'order_tax' => $validated['order_tax'] ?? 0,
            'order_total' => $validated['order_total'],
            'created_by' => $uid,
            'order_type' => null,
        ]);

        $order_id = $order_masters->order_id;
        $order_items = [];
        // $order_details = [];

        foreach ($validated['items'] as $item) {
            $order_items[] = OrderItems::create([
                'order_id' => $order_id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'item_price' => $item['item_price'],
                'discount' => $item['discount'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'] ?? 0,
                'item_wholesale_price' => $item['item_wholesale_price'] ?? 0,
                'exchange_rate' => (double)$exchange_rate->usd_to_khr,
            ]);
        }

        // $message = $validated['order_tel'];
        // $user = $user->username;

        // Broadcast to Pusher
        broadcast(new PrivateChannelEvent("New order by" . $validated['order_tel'], (int)$proId))->toOthers();
        return $this->show($order_masters->order_id);
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
            ->where('om.created_by', $uid)
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
            'delivery_fee' => 'numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_subtotal' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_discount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_tax' => 'nullable|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'order_total' => 'required|numeric|min:0|max:99999999.99',
            'balance' => 'required|numeric|min:0|max:99999999.99',
            'payment' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            // 'order_type' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.item_name' => 'required|string|max:255',
            'items.*.discount' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.quantity' => 'required|integer',
            'items.*.item_cost' => 'required|numeric',
            'items.*.item_wholesale_price' => 'required|numeric',
        ]);

        // Create the order master
        $order_masters->update([
            // 'order_no' => $order_no,
            'order_tel' => $validated['order_tel'],
            'order_address' => $validated['order_address'],
            'order_date' => $validated['order_date'],
            'delivery_fee' => $validated['delivery_fee'],
            'order_payment_status' => $validated['order_payment_status'],
            'order_payment_method' => $validated['order_payment_method'],
            'balance' => $validated['balance'],
            'payment' => $validated['payment'],
            'order_subtotal' => $validated['order_subtotal'],
            'order_discount' => $validated['order_discount'],
            'order_tax' => $validated['order_tax'] ?? 0,
            'order_total' => $validated['order_total'],
            'order_type' => null,
        ]);

        $order_items = [];
        if ($order_masters) {
            OrderItems::where('order_id', $id)->delete();
        }
        foreach ($validated['items'] as $item) {
            $order_items[] = OrderItems::create([
                'order_id' => $order_masters->order_id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'item_price' => $item['unit_price'],
                'discount' => $item['discount'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item_cost'] ?? 0,
                'item_wholesale_price' => $item['item_wholesale_price'] ?? 0,
                'exchange_rate' => (double)$exchange_rate->usd_to_khr,
            ]);
        }

        return $this->show($id);
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
            'data' => $order_master
        ]);
    }

    public function cancel(string $id)
    {
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'is_cancelled' => 1,
        ]);
        // $orderItems->update([
        //     'is_cancelled' => 1,
        // ]);
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
            'data' => $orders->first()
        ]);
    }

    public function uncancel(string $id)
    {
        $orders = OrderMaster::where('order_id', $id);
        // $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'is_cancelled' => 0,
        ]);
        // $orderItems->update([
        //     'is_cancelled' => 0,
        // ]);
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
            'data' => $orders->first()
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
        if (!$order_items->isEmpty()) {
            foreach ($order_items as $item) {
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
    public function viewOrder(string $id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $orders = OrderMaster::where('order_id', $id);
        $orderItems = OrderItems::where('order_id', $id);
        if (!$orders) {
            return response()->json([
                'message' => 'order not found!',
                'status' => 404,
            ]);
        }
        $orders->update([
            'status' => 3,
        ]);
        if (!$order_items->isEmpty()) {
            foreach ($order_items as $item) {
                $item->update([
                    'status' => 3,
                ]);
            }
        }
        broadcast(new OnlineEvent('view order', $proId))->toOthers();
        return $this->show($id);
    }


    public function orderTransection(){
        $user = Auth::user();
        $proId = $user->profile_id;
        $orders = DB::table('order_items as oi')
        ->join('order_masters as om','oi.order_id','=','om.order_id')
        ->join('items as i','oi.item_id','=','i.item_id')
        ->join('categories as c', 'c.category_id', '=', 'i.category_id')
        ->join('brands as b', 'b.brand_id', '=', 'i.brand_id')
        ->join('users as u','om.created_by','=','u.id')
        ->join('profiles as p','u.profile_id','=','p.id')
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
        DB::raw('SUM(CASE WHEN om.sale_type = "sale" THEN oi.quantity * oi.item_price ELSE oi.quantity * oi.item_wholesale_price END) AS amount_sold'),
        )
        ->where('om.is_deleted',0)
        ->where('p.id',$proId)
        ->groupBy('i.item_id','i.item_name','i.barcode','i.item_code')
        ->get();
        if ($orders->isEmpty()) {
            return response()->json([
                'message' => 'order transection not found!',
                'status' => 404,
            ]);
        }

        foreach ($orders as $order) {
            $images = $this->itemService->getImage($order->item_id);
            $attrs = $this->attributeService->transformAttributes($order->item_id);
            $order->attributes = $attrs??null;
            $order->image = $images[0] ?? null;
            $order->images = $images ?? [];
        }

        return response()->json([
            'message' => 'order transection fetched successfully!',
            'status' => 200,
            'data' => $orders,
        ]);
    }
}
