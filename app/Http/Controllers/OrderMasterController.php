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

    public function index()
    {
        $user = auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $orderMasters = DB::table('order_masters as om')
            ->join('customers as cu','om.order_customer_id','=',"cu.customer_id")
            ->join('delivers as dl','om.deliver_id','=',"dl.deliver_id")
            ->join('users', 'om.created_by', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('om.is_deleted', 0)
            ->where('profiles.id', $proId)
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
    public function orderByUser()
    {
        $user = auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $orderMasters = DB::table('order_masters as om')
            ->join('customers as cu','om.order_customer_id','=',"cu.customer_id")
            ->join('delivers as dl','om.deliver_id','=',"dl.deliver_id")
            ->join('users', 'om.created_by', '=', 'users.id')
            ->where('om.is_deleted', 0)
            ->where('om.created_by', $uid)
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
        $order_no = 'ORD' . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
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
            'items.*.discount' => 'required|numeric',
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
            'online' => $validated['online'],
            'status' => $validated['status'],
            'order_tel' => $validated['order_tel'],
            'deliver_id' => $validated['deliver_id'],
            'order_address' => $validated['order_address'],
            'order_date' => $order_date,
            'delivery_fee' => $validated['delivery_fee'],
            'through' => $validated['through'] ?? $uid,
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


        if($validated['online'] == 1){
            $customer = Customers::find($validated['order_customer_id']);
            $profile_id = Users::where('id', $validated['through'])->value('profile_id');
            $profile = DB::table('profiles')->where('id', $profile_id)->first();

           $phone = $validated['order_tel']
                ?? $customer->customer_tel
                ?? 'N/A';

            $address = $validated['order_address']
                ?? $customer->customer_address
                ?? 'N/A';

            // Build items list dynamically
            $itemsList = '';
                foreach ($validated['items'] as $item) {
                    $itemsList .=
                        "\t\t• <b>{$item['item_name']}</b> |  Qty: {$item['quantity']}  |  Price: <b>\$" . ($item['price'] * $item['quantity'])."</b>\n";
                }


             $message =
                "🛒 <b>New Order Received</b>

                🏪 <b>Shop:</b> {$profile->profile_name}
                🆔 <b>Order No:</b> {$order_no}

                📞 <b>Buyer Phone:</b> " . ($customer->customer_tel ?? $user->phone_number) . "
                📦 <b>Recipient Phone:</b> {$phone}

                📌 <b>Address:</b> " . ($address ?? 'N/A') . "
                🗺️ <b>Commune:</b> " . ($customer->customer_communes ?? 'N/A') . "
                🏙️ <b>District:</b> " . ($customer->customer_districts ?? 'N/A') . "
                🌆 <b>Province:</b> " . ($customer->customer_provinces ?? 'N/A') . "
                🏡 <b>Village:</b> " . ($customer->customer_villages ?? 'N/A') . "

                📅 <b>Order Date:</b> {$order_date}

                📦 <b>Items List</b>
                {$itemsList}
                💰 <b>Total:</b> 💵 <b>\${$validated['order_total']}</b>";



                // Broadcast to Pusher
                broadcast(new PrivateChannelEvent("New order by" . $validated['order_tel'], (int)$profile_id))->toOthers();
                TelegramService::sendMessage($message, $profile_id);
        }

        // return $message;
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
            'deliver_id' => 'nullable|integer',
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
            'deliver_id' => $validated['deliver_id'],
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
            'is_cancelled' => 1,
            'status' => 7,
        ]);
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'order cancelled successfully!',
            'status' => 200,
            'data' => $orders->first()
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
            'is_cancelled' => 0,
            'status' => 1,
        ]);

        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
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
        DB::raw('SUM(oi.quantity) AS total_quantity_sold'),
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
        if($status == 7){
            $order->is_cancelled = 1;
            $order->status = $status;
            $order->save();
        }else{
            $order->status = $status;
            $order->is_cancelled = 0;
            $order->save();
        }

        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
        return response()->json([
            'message' => 'new status!',
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
        return response()->json([
            'message' => 'update delivery fee!',
            'status' => 200,
        ]);
        broadcast(new OnlineEvent('New Status', $proId))->toOthers();
    }
}
