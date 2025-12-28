<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AttributeDetail;
use App\Models\AttributeValue;
use App\Models\AttributeValueDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class AttributeValueController extends Controller
{
	public function index()
	{
		$user = Auth::user();
		$uid = $user?->id ?? null;

		$query = AttributeValue::query();

		$values = $query->get();

		if (count($values) == 0) {
			return response()->json([
				'message' => 'Attribute values not found!',
				'status' => 404,
				'data' => $values,
			], 404);
		}

		return response()->json([
			'message' => 'Attribute values selected successfully',
			'status' => 200,
			'data' => array_reverse($values->toArray()),
		], 200);
	}

	public function store(Request $request)
{
    $user = Auth::user();
    $uid = $user?->id ?? null;

    $validated = $request->validate([
        'item_id' => 'required|integer',
        'attribute_id' => 'required|integer',
        'value' => 'nullable', // allow array or string
    ]);

    // If value is array => convert to JSON string
    $arrValue = config($validated['value']);

    $values = is_array($arrValue)
        ? $arrValue
        : (isset($arrValue) ? [$arrValue] : []);


    $payload = [
        'item_id' => $validated['item_id'],
        'attribute_id' => $validated['attribute_id'],
    ];

    $attr_detail = AttributeDetail::create($payload);
    foreach($values as $value){
        $exist = DB::table('attribute_values')->where('value',$value);
        if(!$exist){
            $attribute_value = [
                'value' => $value,
            ];
           $exist = AttributeValue::create($attribute_value);
        }
        $attr_value_detail = [
            'attribute_detail_id'=>$attr_detail->id,
            'attribute_value'=>$exist->id,
        ];
        AttributeValueDetail::create($attr_value_detail);
    }



    return response()->json([
        'message' => 'Attribute value created successfully!',
        'status' => 200,
        'data' => $attr_detail,
    ], 201);
}


	public function show(string $id)
	{
		$value = AttributeValue::find($id);

		if (!$value) {
			return response()->json([
				'message' => 'Attribute value not found!',
			], 404);
		}

		return response()->json([
			'message' => 'Attribute value retrieved successfully!',
			'status' => 200,
			'data' => $value,
		], 200);
	}

	public function update(Request $request, string $id)
	{
		$value = AttributeValue::find($id);

		if (!$value) {
			return response()->json([
				'message' => 'Attribute value not found!',
			], 404);
		}

		$validated = $request->validate([
			'item_id' => 'required|integer',
			'attribute_id' => 'required|integer',
			'value' => 'nullable|string',
		]);

		$value->update($validated);

		return response()->json([
			'message' => 'Attribute value updated successfully',
			'status' => 200,
			'data' => $value,
		], 200);
	}

	public function destroy(string $id)
	{
		$value = AttributeValue::find($id);
		if (!$value) {
			return response()->json([
				'message' => 'Attribute value not found!',
			], 404);
		}


		$value->delete();


		return response()->json([
			'message' => 'Attribute value deleted successfully',
			'status' => 200,
			'data' => $value,
		], 200);
	}

}
