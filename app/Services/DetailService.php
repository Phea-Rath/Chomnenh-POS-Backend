<?php
namespace App\Services;
use App\Models\StockDetails;
use App\Services\AttributeService;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class DetailService {
    protected $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function stockDetail($id) {
        $user = Auth::user();
        $uid = $user->id;

        $stock_detail = DB::table('stock_details')
            ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'stock_details.*',
                'items.item_code',
                'items.barcode',
                'items.item_name',
                'items.item_price',
                'items.wholesale_price',
                'items.discount',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'stock_masters.stock_created_by as created_by'
            )
            // ->where('stock_masters.stock_created_by', $uid)
            ->where('stock_details.is_deleted', 0)
            ->where('items.is_deleted', 0)
            ->where('stock_details.stock_id', $id)
            ->get();

        if ($stock_detail->isEmpty()) {
            return null;
        }

            foreach($stock_detail as $stock){

               $stock->attributes =  $this->attributeService->transformAttributes($stock->item_id);
               $stock->stock = $this->quanItems($stock->item_id)[0];

            }


        // ✅ Get all item_ids from collection
        $itemIds = $stock_detail->pluck('item_id')->unique();

        // ✅ Get images for those items
        $images = DB::table('item_images as ii')
            ->join('images as im', 'im.id', '=', 'ii.image_id')
            ->select('ii.item_id', 'im.image')
            ->whereIn('ii.item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        // ✅ Attach images to each stock item
        $stock_detail->transform(function ($item) use ($images) {
            $item->images = $images[$item->item_id] ?? [];
            if (!empty($item->images)) {
                foreach($item->images as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });

        return $stock_detail;
    }
    public function stockRawDetail($id) {
        $user = Auth::user();
        $uid = $user->id;

        $stock_detail = DB::table('stock_raw_details')
            ->join('stock_masters', 'stock_raw_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('raw_materials', 'stock_raw_details.raw_material_id', '=', 'raw_materials.id')
            ->select(
                'stock_raw_details.*',
                'raw_materials.material_name',
                'raw_materials.material_image',
                'raw_materials.material_code',
                'stock_masters.stock_created_by as created_by'
            )
            // ->where('stock_masters.stock_created_by', $uid)
            ->where('stock_raw_details.is_deleted', 0)
            ->where('stock_raw_details.stock_id', $id)
            ->where('raw_materials.is_deleted', 0)
            ->get();

        if ($stock_detail->isEmpty()) {
            return null;
        }

            foreach($stock_detail as $stock){
                if($stock->material_image){
                    $filenameOnly = basename($stock->material_image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $stock->material_image = $imageUrl;
                }
                $stock->stock = $this->quanRaws($stock->raw_material_id)[0];

            }

        return $stock_detail;
    }

    public function purchaseDetail($id) {
        $user = Auth::user();
        $uid = $user->id;

        $purchase_detail = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.purchase_id')
            ->join('items', 'purchase_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'purchase_details.*',
                'items.item_code',
                'items.item_name',
                'items.item_price',
                'items.wholesale_price',
                'items.discount',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'purchases.created_by as created_by'
            )
            // ->where('purchases.created_by', $uid)
            ->where('purchase_details.is_deleted', 0)
            ->where('purchase_details.purchase_id', $id)
            ->get();

        if ($purchase_detail->isEmpty()) {
            return response()->json([
                "message" => "No purchase detail found",
                "status" => 404,
                "data" => []
            ]);
        }

        foreach($purchase_detail as $purchase){

            $purchase->attributes = $this->attributeService->transformAttributes($purchase->item_id);
        }

        // ✅ Get all item_ids from collection
        $itemIds = $purchase_detail->pluck('item_id')->unique();

        // ✅ Get images for those items
        $images = DB::table('item_images as ii')
            ->join('images as im', 'im.id', '=', 'ii.image_id')
            ->select('ii.item_id', 'im.image')
            ->whereIn('ii.item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        // ✅ Attach images to each purchase item
        $purchase_detail->transform(function ($item) use ($images) {
            $item->images = $images[$item->item_id] ?? [];
            if (!empty($images[$item->item_id])) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });

        return $purchase_detail;
    }


    public function purchaseRawDetail($id) {
        $user = Auth::user();
        $uid = $user->id;

        $purchase_detail = DB::table('purchase_raw_details')
            ->join('purchases', 'purchase_raw_details.purchase_id', '=', 'purchases.purchase_id')
            ->join('raw_materials', 'purchase_raw_details.raw_material_id', '=', 'raw_materials.id')
            ->select(
                'purchase_raw_details.*',
                'raw_materials.material_code',
                'raw_materials.material_name',
                'raw_materials.material_image',
                'raw_materials.is_deleted',
                'purchases.created_by'
            )
            // ->where('purchases.created_by', $uid)
            ->where('purchase_raw_details.is_deleted', 0)
            ->where('purchase_raw_details.purchase_id', $id)
            ->get();

        if ($purchase_detail->isEmpty()) {
            return response()->json([
                "message" => "No purchase detail found",
                "status" => 404,
                "data" => []
            ]);
        }


        // ✅ Attach images to each purchase item

        return $purchase_detail;
    }

    public function purchaseDetailById($id) {
        $user = Auth::user();
        $uid = $user->id;

        $purchase_detail = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.purchase_id')
            ->join('items', 'purchase_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'purchase_details.*',
                'items.item_code',
                'items.item_name',
                'items.item_price',
                'items.wholesale_price',
                'items.discount',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'purchases.created_by as created_by'
            )
            // ->where('purchases.created_by', $uid)
            ->where('purchase_details.is_deleted', 0)
            ->where('purchase_details.id', $id)
            ->get();

        if ($purchase_detail->isEmpty()) {
            return response()->json([
                "message" => "No purchase detail found",
                "status" => 404,
                "data" => []
            ]);
        }

        foreach($purchase_detail as $purchase){
            $attrs = DB::table('purchase_attributes')->where('purchase_detail_id',$purchase->id)->get();

            $purchase_attrs = [];
            foreach($attrs as $attr){

                $data = ['item_id'=>$attr->item_id,'name_id'=>$attr->attribute_id,'value_id'=>$attr->attribute_value_id];
                $request = new Request($data);
                array_push($purchase_attrs, $this->attributeService
                ->attrUnit($request)[0]);

            }
            $purchase->attributes = $purchase_attrs;
        }

        // ✅ Get all item_ids from collection
        $itemIds = $purchase_detail->pluck('item_id')->unique();

        // ✅ Get images for those items
        $images = DB::table('item_images as ii')
            ->join('images as im', 'im.id', '=', 'ii.image_id')
            ->select('ii.item_id', 'im.image')
            ->whereIn('ii.item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        // ✅ Attach images to each purchase item
        $purchase_detail->transform(function ($item) use ($images) {
            $item->images = $images[$item->item_id] ?? [];
            if (!empty($images[$item->item_id])) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });

        return $purchase_detail;
    }

    public function orderDetailById($id) {
        $user = Auth::user();
        $uid = $user->id;

        $order_item = DB::table('order_items')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->join('items', 'order_items.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'order_items.*',
                'items.item_name',
                'items.item_code',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'order_masters.created_by as created_by'
            )
            // ->where('order_masters.created_by', $uid)
            ->where('order_items.is_deleted', 0)
            ->where('order_items.order_id', $id)
            ->get();

        if ($order_item->isEmpty()) {
            return response()->json([
                "message" => "No order item found",
                "status" => 404,
                "data" => []
            ]);
        }

        $order_item = $order_item->map(function ($order) {
            $order->in_stock = $this->quanItems($order->item_id)[0]->in_stock;
            $order->stock = $this->quanItems($order->item_id)[0];
            $order->attributes = $this->attributeService
                ->transformAttributes($order->item_id);

            return $order;
        });


        // ✅ Get all item_ids from collection
        $itemIds = $order_item->pluck('item_id')->unique();

        // ✅ Get images for those items
        $images = DB::table('item_images as ii')
            ->join('images as im', 'im.id', '=', 'ii.image_id')
            ->select('ii.item_id', 'im.image')
            ->whereIn('ii.item_id', $itemIds)
            ->get()
            ->groupBy('item_id');

        // ✅ Attach images to each order item
        $order_item->transform(function ($item) use ($images) {
            $item->images = $images[$item->item_id] ?? [];
            if (!empty($images[$item->item_id])) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });



        return $order_item;
    }


    public function quanItems($item_id) {
        $user = auth()->user();
        $proId = $user->profile_id ?? 0;
        $query = DB::table('stock_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('items as i', 'sd.item_id', '=', 'i.item_id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('i.is_deleted', 0)
            ->where('i.item_id', $item_id);
        if($proId){
            $query->where('p.id', $proId);
        }
        $items = $query->select(
                DB::raw('COALESCE(
                    SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END)
                    + SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END),0) AS in_stock'),

                DB::raw('COALESCE(SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END),0) AS stock_return'),
                DB::raw('COALESCE(SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END),0) AS stock_in'),
                DB::raw('COALESCE(SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END),0) AS stock_out'),
                DB::raw('COALESCE(SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END),0) AS stock_wasted'),
                DB::raw('COALESCE(SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END),0) AS sold')
            )
            ->get();

            $totalOrdered = DB::table('order_items as oi')
            ->join('order_masters as om', 'oi.order_id', '=', 'om.order_id')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->where('oi.item_id', $item_id)
            ->where('oi.is_deleted', 0)
            ->where('om.is_deleted', 0)
            ->whereIn('om.status', [4,5,6])
            ->sum('oi.quantity');
            if(!$totalOrdered){
                $totalOrdered = 0;
            }
            foreach ($items as $it) {
                $it->stock_return = (int) $it->stock_return;
                $it->stock_in = (int) $it->stock_in;
                $it->stock_out = (int) $it->stock_out;
                $it->stock_wasted = (int) $it->stock_wasted;
                $it->sold = (int) $totalOrdered;

                $it->in_stock = (int) $it->in_stock - $totalOrdered;
            }


            return $items;
        }


    public function quanRaws($item_id) {
        $user = auth()->user();
        $proId = $user->profile_id;
        $query = DB::table('stock_raw_details as sd')
            ->join('stock_masters as sm', 'sd.stock_id', '=', 'sm.stock_id')
            ->join('raw_materials as i', 'sd.raw_material_id', '=', 'i.id')
            ->join('users as u', 'sm.stock_created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('sd.is_deleted', 0)
            ->where('sm.is_deleted', 0)
            ->where('i.is_deleted', 0)
            ->where('i.id', $item_id)
            ->where('p.id', $proId);


        $items = $query->select(
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END)
                    + SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END)
                    - SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END)
                    AS in_stock'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 1 THEN sd.quantity ELSE 0 END) AS stock_return'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 2 THEN sd.quantity ELSE 0 END) AS stock_in'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 3 THEN sd.quantity ELSE 0 END) AS stock_out'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 4 THEN sd.quantity ELSE 0 END) AS stock_wasted'),
                DB::raw('SUM(CASE WHEN sm.stock_type_id = 5 THEN sd.quantity ELSE 0 END) AS sold')
            )
            ->orderBy('i.id')
            ->get();

            $totalOrdered = DB::table('production_details as oi')
            ->join('productions as om', 'oi.production_id', '=', 'om.id')
            ->join('users as u', 'om.created_by', '=', 'u.id')
            ->join('profiles as p', 'u.profile_id', '=', 'p.id')
            ->where('p.id', $proId)
            ->where('oi.raw_material_id', $item_id)
            ->where('oi.is_deleted', 0)
            ->where('om.is_deleted', 0)
            ->sum('oi.quantity');
            if(!$totalOrdered){
                $totalOrdered = 0;
            }
            foreach($items as $it){
                $productionQuan = $totalOrdered;
                if($productionQuan){
                    $it->sold = $productionQuan;
                    $it->in_stock = $it->in_stock - $productionQuan;
                }
            }

            return $items;
        }


        // dd($totalQuan);
    function calculateTotalCost($table, $item_label, $item_id, $requiredQuantity)
    {
        $resQuantity = (int)$this->quanRaws($item_id)[0]->in_stock ?? 0;
        $records = DB::table($table)
            ->where($item_label, $item_id)
            ->where('is_deleted', 0)
            ->orderBy('created_at', 'desc') // rest stock first (FIFO)
            ->get();

        $remaining = $resQuantity;
        $restTotalCost = 0;
        $usedRecords = [];

        foreach ($records as $record) {
            if ($remaining <= 0) break;

            $takeRestQty = min($record->quantity, $remaining); // only take what we need

            $restTotalCost += $takeRestQty * $record->item_cost;

            // Save only the quantity we actually used
            $usedRecords[] = [
                'detail_id' => $record->id,
                'stock_id' => $record->stock_id,
                'item_id' => $record->raw_material_id,
                'created_at' => $record->created_at,
                'item_cost' => $record->item_cost,
                'used_quantity' => $takeRestQty,
                'remaining_quantity' => $record->quantity - $takeRestQty,
            ];

            $remaining -= $takeRestQty;
        }
        

        $sorted = collect($usedRecords)
        ->sortBy('created_at')
        ->values();

        $remaining = $requiredQuantity;
        $totalCost = 0;

        foreach ($sorted as $row) {
            if ($remaining <= 0) break;

            $takeQty = min($row['used_quantity'], $remaining);
            $totalCost += $takeQty * (float)$row['item_cost'];
            $remaining -= $takeQty;
        }

        return [
            'usedCount' => count($usedRecords),
            // 'records' => $usedRecords,
            // 'remainingQuantity' => $remaining // how much we couldn’t fulfill
            'requiredQty' => $requiredQuantity,
            'restQuantiy' => $resQuantity,
            'resTotalCost' => $restTotalCost,
            'totalCost' => $totalCost,
            'fulfilledQty' => $requiredQuantity - $remaining,
            'notFulfilledQty' => $remaining
        ];
    }
}
