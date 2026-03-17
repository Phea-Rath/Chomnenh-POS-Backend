<?php

namespace App\Http\Controllers;

use App\Events\OnlineEvent;
use App\Events\OrderMessage;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ItemService;
use App\Services\AttributeService;
use App\Services\DetailService;

class NotificationController extends Controller
{
    protected $attributeService;
    protected $itemService;
    protected $detailService;


    public function __construct(AttributeService $attributeService, ItemService $itemService, DetailService $detailService)
    {
        $this->attributeService = $attributeService;
        $this->itemService = $itemService;
        $this->detailService = $detailService;
    }
    public function index()
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $results = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('warehouses as w', 'sm.warehouse_id', '=', 'w.warehouse_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('categories as c', 'i.category_id', '=', 'c.category_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sm.warehouse_id', 1)
            ->where('w.status', 'stock')
            ->where('sd.is_deleted', 0)
            ->where('i.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('p.id', $proId)
            ->whereDate('sd.expire_date', '<=', Carbon::now()->toDateString())
            ->groupBy('sd.item_id', 'i.item_name', 'c.category_name')
            ->select(
                'sd.item_id',
                'i.item_name',
                'c.category_name',
                DB::raw('SUM(sd.quantity) as wasted_qty'),
                DB::raw('MAX(sd.expire_date) as last_expire_date')
            )
            ->orderBy('last_expire_date', 'desc')
            ->get();

        return response()->json([
            'message' => 'Wasted items retrieved successfully!',
            'status' => 200,
            'data' => $results
        ], 200);
    }


    public function orderOnline()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        $orderMasters = DB::table('order_masters as om')
            ->join('users', 'om.through', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->join('delivers', 'om.deliver_id', '=', 'delivers.deliver_id')
            ->where('profiles.id', $proId)
            ->where('om.is_deleted', 0)
            ->where('om.online', 1)
            // ->where('om.status','!=', 6)
            ->select('om.*', 'delivers.deliver_name', 'delivers.image as deliver_image')
            ->orderBy('om.order_id', 'desc')
            ->get();
        foreach ($orderMasters as $item) {
            if ($item->deliver_image) {
                $filenameOnly = basename($item->deliver_image);
                $item->deliver_image = url('storage/images/' . $filenameOnly);
            }
        }

        if ($orderMasters->isEmpty()) {
            return response()->json([
                'message' => 'Order online get fail!',
                'status' => 404,
                'data' => []
            ]);
        }

        // Attach items to each order
        $ordersWithItems = $orderMasters->map(function ($order) {
            $order->items = DB::table('order_items as oi')
                ->join('items as i', 'oi.item_id', '=', 'i.item_id')
                ->join('categories as c', 'i.category_id', '=', 'c.category_id')
                ->select(
                    'i.item_name',
                    'i.item_code',
                    'i.category_id',
                    'c.category_name',
                    'oi.*'
                )
                ->where('oi.is_deleted', 0)
                ->where('order_id', $order->order_id)
                ->get();

            // Fix: loop through each item
            foreach ($order->items as $item) {
                    $item->images = $this->itemService->getImage($item->item_id);
                    $item->item_image = $this->itemService->getImage($item->item_id)[0]['image'] ?? null;
            }

            return $order;
        });

        // broadcast(new OrderMessage($ordersWithItems))->toOthers();
        return response()->json([
            'message' => 'Order online fetched successfully!',
            'status' => 200,
            'data' => $ordersWithItems,
        ]);
    }


    public function updateWasteItem(Request $request, $id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $request->validate([
            'expire_date_to' => 'required|date',
            'expire_date_item' => 'required|date',
            // 'in_stock' => 'required|integer|min:0',
        ]);

        $user = Auth::user();
        $proId = $user->profile_id;

        // Check if the item exists and belongs to the user's profile
        $itemExists = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.item_id', $id)
            ->where('sm.is_deleted', 0)
            ->whereNotIn('sm.stock_type_id',[2,3,4]) // Ensure it's a waste item
            ->where('sd.expire_date', $request->input('expire_date_item'))
            ->where('p.id', $proId)
            ->exists();

        if (!$itemExists) {
            return response()->json([
                'message' => 'Item not found or does not belong to your profile.',
                'status' => 404,
            ], 404);
        }

        // Update the stock_details table
        DB::table('stock_details')
            ->where('item_id', $id)
            ->where('expire_date', $request->input('expire_date_item'))
            ->update([
                'expire_date' => $request->input('expire_date_to'),
                // 'quantity' => $request->input('in_stock'),
                'updated_at' => now(),
            ]);

        broadcast(new OnlineEvent('update waste', $proId))->toOthers();
        return response()->json([
            'message' => 'Waste item updated successfully!',
            'status' => 200,
        ], 200);
    }
}


