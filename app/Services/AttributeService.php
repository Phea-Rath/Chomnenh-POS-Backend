<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Items;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\AttributeValue;
use App\Models\AttributeDetail;
use App\Models\AttributeValueDetail;

class AttributeService {
    public function transformAttributes($id):array{
        $attrs = DB::table('attribute_value_details as avd')
            ->join('attribute_values as av', 'avd.attribute_value_id', '=', 'av.id')
            ->join('attribute_details as ad', 'avd.attribute_detail_id', '=', 'ad.id')
            ->join('attributes as a', 'ad.attribute_id', '=', 'a.id')
            ->where('ad.item_id' ,$id)
            ->select('a.id as name_id', 'a.name','a.type', 'av.id as value_id', 'av.value')
            ->get();

        // dd($id);

        if(empty($attrs) || count($attrs) < 0){
            return response()->json([
                'status' => 404,
                'message'=> 'Attribute not found',
            ],404);
        }

        $result = collect($attrs)
            ->groupBy('name')
            ->map(function ($items) {

                $first = $items->first();

                return [
                    'id' => $first->name_id,
                    'name' => $first->name,
                    'type' => $first->type,
                    'value' => $first->type === 'select'
                        ? $items->map(function($item) {
                            return [
                                'id' => $item->value_id,    // id of each option
                                'value' => $item->value
                            ];
                        })->values()->toArray()
                        : $first->value,
                ];
            })
            ->values()
            ->toArray();


            return $result;
    }

    public function attrUnit(Request $request):array
    {
        // dd($request);
        $request->validate([
            'item_id'  => 'required|integer',
            'name_id'  => 'required|integer',
            'value_id' => 'required|integer',
        ]);

        $attrs = DB::table('attribute_value_details as avd')
            ->join('attribute_values as av', 'avd.attribute_value_id', '=', 'av.id')
            ->join('attribute_details as ad', 'avd.attribute_detail_id', '=', 'ad.id')
            ->join('attributes as a', 'ad.attribute_id', '=', 'a.id')
            ->where('ad.item_id', $request->item_id)
            ->where('ad.attribute_id', $request->name_id)
            ->where('avd.attribute_value_id', $request->value_id)
            ->select(
                'a.id as name_id',
                'a.name',
                'av.id as value_id',
                'av.value'
            )
            ->get()->toArray();


        return $attrs;
    }
}
?>
