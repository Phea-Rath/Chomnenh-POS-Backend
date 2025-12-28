<?php

namespace App\Http\Controllers;

use App\Events\OnlineEvent;
use App\Events\OrderMessage;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        $results = DB::table('stock_details')
            ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('warehouses', 'stock_masters.warehouse_id', '=', 'warehouses.warehouse_id')
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->join('sizes', 'items.size_id', '=', 'sizes.size_id')
            ->join('users', 'stock_masters.stock_created_by', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->select(
                'items.item_id',
                'items.item_code',
                'items.item_name',
                'items.item_image',
                'items.item_price',
                'categories.category_name',
                'categories.category_id',
                'items.color_id',
                'items.color_pick',
                'sizes.size_id',
                'sizes.size_name',
                'stock_details.expire_date',
                DB::raw(' 
            SUM(CASE WHEN stock_masters.stock_type_id = 1 THEN stock_details.quantity ELSE 0 END) 
            + SUM(CASE WHEN stock_masters.stock_type_id = 2 THEN stock_details.quantity ELSE 0 END) 
            - SUM(CASE WHEN stock_masters.stock_type_id = 3 THEN stock_details.quantity ELSE 0 END) 
            - SUM(CASE WHEN stock_masters.stock_type_id = 4 THEN stock_details.quantity ELSE 0 END) 
            - SUM(CASE WHEN stock_masters.stock_type_id = 5 THEN stock_details.quantity ELSE 0 END) 
            AS in_stock ')
            )->where('warehouses.status', 'stock')
            ->where('stock_details.is_deleted', 0)
            ->where('items.item_type', 0)
            ->where('items.is_deleted', 0)
            ->where('profiles.id', $proId)
            // ->where("users.id", $uid)
            ->where('warehouses.warehouse_id', function ($query) {
                $query->selectRaw('MIN(w.warehouse_id)')
                    ->from('warehouses as w')
                    ->join('stock_masters as sm', 'sm.warehouse_id', '=', 'w.warehouse_id')
                    ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
                    ->whereColumn('u.profile_id', 'profiles.id');
            })
            ->whereDate('stock_details.expire_date', '<=', Carbon::now()->toDateString())
            // ->whereDate('stock_details.expire_date', '<=', Carbon::now()->addDays(3)->toDateString())
            // ->where('stock_masters.stock_type_id', '!=', 4)
            // ->whereIn('stock_masters.stock_type_id', [1, 2])
            ->groupBy(
                'items.item_id',
                'items.item_code',
                'items.item_name',
                'items.item_image',
                'items.item_price',
                'categories.category_name',
                'categories.category_id',
                'items.color_pick',
                'items.color_id',
                'sizes.size_id',
                'sizes.size_name',
                'stock_details.expire_date',
            )
            ->orderBy('items.item_id')->get();
        $newData = [];
        foreach ($results as $item) {
            if ($item->in_stock <= 0) {
                continue; // Skip items with in_stock less than or equal to 0
            }
            // $url = asset($item->item_image);
            if ($item->item_image) {
                $filenameOnly = basename($item->item_image);
                $imageUrl = url('storage/images/' . $filenameOnly);
                $item->item_image = $imageUrl;
            }
            $newData[] = $item;
        }
        return response()->json(['message' => 'StockMaster show successfully!', 'status' => 200, 'data' => $newData,], 200);
    }


    public function orderOnline()
    {
        $user = Auth::user();
        // $uid = $user->id;
        $proId = $user->profile_id;
        $orderMasters = DB::table('order_masters as om')
            ->join('users', 'om.created_by', '=', 'users.id')
            ->join('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('om.is_deleted', 0)
            ->whereIn('om.status', [2, 3])
            ->select('om.*')
            ->orderBy('om.order_id', 'desc')
            ->get();

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
                ->join('sizes as s', 'i.size_id', '=', 's.size_id')
                ->select(
                    'i.item_name',
                    'i.item_code',
                    'i.color_id',
                    'i.color_pick',
                    'i.category_id',
                    'c.category_name',
                    'i.size_id',
                    's.size_name',
                    'i.item_image', // make sure you select image
                    'oi.*'
                )
                ->where('oi.is_deleted', 0)
                ->where('order_id', $order->order_id)
                ->get();

            // Fix: loop through each item
            foreach ($order->items as $item) {
                if (!empty($item->item_image)) {
                    $filenameOnly = basename($item->item_image);
                    $item->item_image = url('storage/images/' . $filenameOnly);
                }
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
