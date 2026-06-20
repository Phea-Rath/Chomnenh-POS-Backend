<?php
namespace App\Services;

use App\Models\PurchaseStatus;
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
        // $user = Auth::user();
        // $uid = $user->id;

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
        // $user = Auth::user();
        // $uid = $user->id;

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
            ->where('stock_raw_details.stock_id', $id)
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
        // $user = Auth::user();
        // $uid = $user->id;

        $purchase_detail = DB::table('purchase_details')
            ->join('purchases', 'purchase_details.purchase_id', '=', 'purchases.purchase_id')
            ->join('items', 'purchase_details.item_id', '=', 'items.item_id')
            ->join('scales', 'scales.scale_id', '=', 'items.scale_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'purchase_details.*',
                'items.item_code',
                'items.item_name',
                'scales.scale_name as unit',
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
        // $user = Auth::user();
        // $uid = $user->id;

        $purchase_detail = DB::table('purchase_raw_details')
            ->join('purchases', 'purchase_raw_details.purchase_id', '=', 'purchases.purchase_id')
            ->join('raw_materials', 'purchase_raw_details.raw_material_id', '=', 'raw_materials.id')
            ->select(
                'purchase_raw_details.*',
                'raw_materials.material_code',
                'raw_materials.primary_unit as unit',
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

    public function purchaseStatus(string $purchaseId){
        $query = DB::table("purchase_status as ps")
            ->join("users as u", 'u.id', '=', 'ps.created_by')
            ->where('ps.purchase_id',$purchaseId)
            ->select('u.username as created_by_name','ps.*')->get();
        if(empty($query)){
            return null;
        }

        return $query;
    }

    public function purchaseDetailById($id) {
        // $user = Auth::user();
        // $uid = $user->id;

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

    public function purchasePayment(string $id){
        $payments = DB::table('purchase_payments as  pp')
                ->join('payments as p','pp.payment_id', '=', 'p.payment_id')
                ->where('pp.purchase_id', $id)
                ->select(
                    'p.amount',
                    'p.payment_method',
                    'p.transection_id',
                    'p.remark',
                    'p.paid_at',
                    'p.created_at',
                )
                ->get();

        return $payments;
    }


    public function purchaseShipping(string $id){
        $shippings = DB::table('shipping')
            ->select(
                'shipping.purchase_id',
                'shipping.carrier',
                'shipping.via',
                'shipping.fee',
                'tracking_number'
            )
                ->where('purchase_id', $id)->first();

        return $shippings;
    }

    public function orderPayment(string $id){
        $payments = DB::table('order_payments as  pp')
                ->join('payments as p','pp.payment_id', '=', 'p.payment_id')
                ->where('pp.order_id', $id)
                ->select(
                    'p.amount',
                    'p.payment_method',
                    'p.transection_id',
                    'p.remark',
                    'p.paid_at',
                    'p.created_at',
                )
                ->get();

        return $payments;
    }

    public function orderStatus(string $orderId){
        $query = DB::table('order_tracking as ot')
            ->join("users as u", 'u.id', '=', 'ot.created_by')
            ->where('ot.order_id',$orderId)
            ->select('u.username as created_by_name','ot.*')->get();
        if(empty($query)){
            return null;
        }

        return $query;
    }

    public function orderDetailById($id) {
        // $user = Auth::user();
        // $uid = $user->id;

        $order_item = DB::table('order_items')
            ->join('order_masters', 'order_items.order_id', '=', 'order_masters.order_id')
            ->join('items', 'order_items.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->join('scales', 'items.scale_id', '=', 'scales.scale_id')
            ->select(
                'order_items.*',
                'items.item_name',
                'items.item_code',
                'items.category_id',
                'items.scale_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'scales.scale_name',
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
            $item->image = $images[$item->item_id][0] ?? null;
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
            ->where('sm.is_deleted', 0)
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
            ->where('sm.is_deleted', 0)
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
            ->where('om.is_deleted', 0)
            ->sum('oi.quantity');
            if(!$totalOrdered){
                $totalOrdered = 0;
            }
            foreach($items as $it){
                $productionQuan = $totalOrdered;
                if($productionQuan){
                    $it->used = $productionQuan;
                    $it->in_stock = $it->in_stock - $productionQuan;
                }
            }

            return $items;
        }


        // dd($totalQuan);
    public function calculateTotalCost($table, $item_label, $item_id, $requiredQuantity)
    {
        // FIFO -> oldest stock first
        $records = DB::table($table)
            ->join('stock_masters as sm', 'sm.stock_id', '=', $table.'.stock_id')
            ->where($item_label, $item_id)
            ->where('sm.is_deleted', 0)
            ->where($table.'.quantity', '>', 0)
            ->orderBy('sm.created_at', 'asc')
            ->get();

        $remainingQty = $requiredQuantity;

        $totalCost = 0;

        $totalRestStock = 0;

        $totalRestCost = 0;

        $usedRecords = [];

        foreach ($records as $record) {

            $availableQty = (float)$record->quantity;

            // FIFO consume quantity
            $usedQty = 0;

            if ($remainingQty > 0) {
                $usedQty = min($availableQty, $remainingQty);
            }

            // Remaining stock after usage
            $restQty = $availableQty - $usedQty;

            $itemCost = (float)$record->item_cost;

            // Used cost
            $lineCost = $usedQty * $itemCost;

            // Remaining cost
            $restCost = $restQty * $itemCost;

            $totalCost += $lineCost;

            $totalRestStock += $restQty;

            $totalRestCost += $restCost;

            $usedRecords[] = [
                'detail_id' => $record->id ?? $record->detail_id,

                'stock_id' => $record->stock_id ?? null,

                'item_id' => $record->raw_material_id ?? $record->item_id,

                'item_cost' => $itemCost,

                'available_quantity' => $availableQty,

                'used_quantity' => $usedQty,

                'rest_quantity' => $restQty,

                'line_cost' => $lineCost,

                'rest_cost' => $restCost,

                'created_at' => $record->created_at,
            ];

            // Reduce remaining required quantity
            $remainingQty -= $usedQty;
        }

        return [
            'requiredQty' => $requiredQuantity,

            'fulfilledQty' => $requiredQuantity - max($remainingQty, 0),

            'notFulfilledQty' => max($remainingQty, 0),

            // Used stock cost
            'totalCost' => $totalCost,

            // Remaining stock quantity
            'restStock' => $totalRestStock,

            // Remaining stock value
            'restCost' => $totalRestCost,

            'usedCount' => count($usedRecords),

            'records' => $usedRecords,
        ];
    }
}
