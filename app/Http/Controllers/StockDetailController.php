<?php

namespace App\Http\Controllers;

use App\Models\StockDetails;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AttributeService;
use App\Services\DetailService;

class StockDetailController extends Controller
{
     protected $attributeService;
     protected $detailService;

    public function __construct(AttributeService $attributeService, DetailService $detailService)
    {
        $this->attributeService = $attributeService;
        $this->detailService = $detailService;
    }

    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;

        $stock_detail = DB::table('stock_details')
            ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                'stock_details.*',
                'items.item_name',
                'items.item_code',
                'items.category_id',
                'items.is_deleted as item_is_deleted',
                'categories.category_name',
                'stock_masters.stock_created_by as created_by'
            )
            ->where('stock_masters.stock_created_by', $uid)
            ->where('stock_details.is_deleted', 0)
            ->get();

        if ($stock_detail->isEmpty()) {
            return response()->json([
                "message" => "No stock detail found",
                "status" => 404,
                "data" => []
            ]);
        }

        foreach($stock_detail as $stock){
            $attrs = DB::table('stock_attributes')->where('stock_detail_id',$stock->detail_id)->get();
            // dd($attrs);

            $stock_attrs = [];
            foreach($attrs as $attr){

                $data = ['item_id'=>$attr->item_id,'name_id'=>$attr->attribute_id,'value_id'=>$attr->attribute_value_id];
                $request = new Request($data);
                array_push($stock_attrs, $this->attributeService
                ->attrUnit($request)[0]);

            }
            // dd($stock_attrs);
            $stock->attributes = $stock_attrs;
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
            if ($images[$item->item_id]) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });



        return response()->json([
            "message" => "Stock detail get successfully",
            "status" => 200,
            "data" => $stock_detail,
        ]);
    }


    public function groupByItems()
    {
        $user = Auth::user();
        $uid = $user->id;

        $stock_detail = DB::table('stock_details')
            ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
            ->join('items', 'stock_details.item_id', '=', 'items.item_id')
            ->join('categories', 'items.category_id', '=', 'categories.category_id')
            ->select(
                // 'stock_details.attributes',
                'stock_details.detail_id',
                'stock_details.item_id',
                // 'items.item_name',
                // 'items.item_code',
                // 'items.category_id',
                // 'items.is_deleted as item_is_deleted',
                // 'categories.category_name',
                // 'stock_masters.stock_created_by as created_by'

                DB::raw('Sum(stock_details.quantity) as quantity'),
            )
            ->where('stock_masters.stock_created_by', $uid)
            ->where('stock_details.is_deleted', 0)
            ->groupBy('stock_details.detail_id', 'stock_details.item_id')
            ->get();

        if ($stock_detail->isEmpty()) {
            return response()->json([
                "message" => "No stock detail found",
                "status" => 404,
                "data" => []
            ]);
        }

        foreach($stock_detail as $stock){
            $attrs = DB::table('stock_attributes')->where('stock_detail_id',$stock->detail_id)->get();

            $stock_attrs = [];
            foreach($attrs as $attr){

                $data = ['item_id'=>$attr->item_id,'name_id'=>$attr->attribute_id,'value_id'=>$attr->attribute_value_id];
                $request = new Request($data);
                array_push($stock_attrs, $this->attributeService
                ->attrUnit($request)[0]);

            }
            $stock->attributes = $stock_attrs;
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
            if ($images[$item->item_id]) {
                foreach($images[$item->item_id] as $image){
                    $filenameOnly = basename($image->image);
                    $imageUrl = url('storage/images/' . $filenameOnly);
                    $image->image = $imageUrl;
                }
            }
            return $item;
        });

        return response()->json([
            "message" => "Stock detail get successfully",
            "status" => 200,
            "data" => $stock_detail,
        ]);
    }



    public function show(string $id)
    {

        $stock_detail = $this->detailService->stockDetail($id);
        // if(is_array($stock_detail)){
        //     return $stock_detail;
        // }
        return response()->json([
            "message" => "Stock detail get successfully",
            "status" => 200,
            "data" => $stock_detail,
        ]);
    }

    public function quantityInStockByItemId(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $pid = $user->profile_id;
        $item_id = $request->item_id;

        $stock_item = DB::table('stock_details')
           ->join('stock_attributes', 'stock_details.detail_id', '=', 'stock_attributes.stock_detail_id')
           ->join('stock_masters', 'stock_details.stock_id', '=', 'stock_masters.stock_id')
           ->join('users', 'stock_masters.stock_created_by', '=', 'users.id')
              ->where('users.profile_id', $pid)
           ->select('stock_details.item_id',
           'stock_attributes.stock_detail_id'
           , DB::raw('CASE WHEN stock_masters.stock_type_id = 1 THEN stock_details.quantity ELSE 0 END
           + CASE WHEN stock_masters.stock_type_id = 2 THEN stock_details.quantity ELSE 0 END
           - CASE WHEN stock_masters.stock_type_id = 3 THEN stock_details.quantity ELSE 0 END
           - CASE WHEN stock_masters.stock_type_id = 4 THEN stock_details.quantity ELSE 0 END
           - CASE WHEN stock_masters.stock_type_id = 5 THEN stock_details.quantity ELSE 0 END
            as quantity_in_stock'))
           ->groupBy('stock_details.item_id','stock_attributes.stock_detail_id')
           ->get();


        if ($stock_item->isEmpty()) {
            return response()->json([
                "message" => "No stock item found",
                "status" => 404,
                "data" => []
            ]);
        }
        $totalQuan = 0;
        foreach ($stock_item as $item) {
            $totalQuan += $item->quantity_in_stock;
            $attrs = DB::table('stock_attributes')
                ->where('stock_detail_id', $item->stock_detail_id)
                ->get();

            $item_attrs = [];
            foreach ($attrs as $attr) {
                $data = [
                    'item_id' => $attr->item_id,
                    'name_id' => $attr->attribute_id,
                    'value_id' => $attr->attribute_value_id
                ];
                $req = new Request($data);
                array_push($item_attrs, $this->attributeService->attrUnit($req)[0]);
            }
            $item->attributes = $item_attrs;
        }

        $result = [];

        foreach ($stock_item as $row) {

            // ALWAYS rebuild attribute key per row
            $attrKeyParts = [];

            foreach ($row->attributes as $attr) {
                // must include BOTH name_id and value_id
                $attrKeyParts[] = $attr->name_id . ':' . $attr->value_id;
            }

            // stock-safe
            sort($attrKeyParts);

            // FINAL UNIQUE KEY
            $key = $row->item_id . '|' . implode('|', $attrKeyParts);

            if (!isset($result[$key])) {
                // clone row to avoid reference issues
                $result[$key] = $row;
                $result[$key]->quantity_in_stock = (int)$row->quantity_in_stock;
            } else {
                $result[$key]->quantity_in_stock += (int)$row->quantity_in_stock;
            }
        }

        $finalData = array_values($result);



        return response()->json([
            "message" => "Order item get successfully",
            "status" => 200,
            "total_quantity" => $totalQuan,
            "data" => $finalData,
        ]);
    }
}
