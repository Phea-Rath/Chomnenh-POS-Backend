<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;
use App\Models\Items;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\AttributeValue;
use App\Models\AttributeDetail;
use App\Models\AttributeValueDetail;
use App\Services\AttributeService;

class AttributeController extends Controller
{
    protected $attributeService;

    public function __construct(AttributeService $attributeService)
    {
        $this->attributeService = $attributeService;
    }

    public function index()
    {
        $user = Auth::user();
        $uid = $user->id;
        $role = $user->role_id;
        $proId = $user->profile_id;

        if ($role == 1) {
            $attributes = DB::table('attributes')
                ->join('users', 'attributes.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->select('attributes.*')
                ->get();
        } else {
            $attributes = DB::table('attributes')
                ->join('users', 'attributes.created_by', '=', 'users.id')
                ->where('users.profile_id', $proId)
                ->where('attributes.created_by', $uid)
                ->select('attributes.*')
                ->get();
        }

        if (count($attributes) == 0) {
            return response()->json([
                'message' => 'Attributes not found!',
                'status' => 404,
                'data' => $attributes
            ], 404);
        }

        return response()->json([
            'message' => 'Attributes selected successfully',
            'status' => 200,
            'data' => array_reverse($attributes->toArray()),
        ], 200);
    }

    public function store(Request $request)
    {

        $attributes = json_decode($request->input('attributes'), true);
        // $attributes = $request->input('attributes');
        $category_id = $request->category_id;
        $item_id = Items::max("item_id");
        $edit_id = $request->input('edit_id');
        // dd($attributes);
        foreach ($attributes as $attr) {

            // 🔍 Check if attribute already exists
            $id = Attribute::where('name', $attr['name'])->pluck('id')->first();

            if ($id) {
                // Attribute exists → only insert value
                // AttributeValue::create([
                //     'item_id' => $item_id,
                //     'attribute_id' => $existing->id,
                //     'value' => $attr['value']
                // ]);
                $arrValue = $attr['value'];

                $values = array_map('trim', explode(',', $arrValue ));

                // dd($values);

                $payload = [
                    'item_id' => $edit_id,
                    'attribute_id' => $id,
                ];

                $attr_detail = AttributeDetail::create($payload);
                foreach($values as $value){
                    $id = DB::table('attribute_values')->where('value',$value)->value('id');
                    if(!$id){
                        $attribute_value = [
                            'value' => $value,
                        ];
                    $id = AttributeValue::create($attribute_value)->id;
                    }
                    $attr_value_detail = [
                        'attribute_detail_id'=>$attr_detail->id,
                        'attribute_value_id'=>$id,
                    ];
                    AttributeValueDetail::create($attr_value_detail);
                }

                continue; // skip creating a new attribute
            }
            $user = Auth::user();
            $uid = $user->id;

            // 🆕 Create new attribute
            $attribute = Attribute::create([
                'name' => $attr['name'],
                'type' => $attr['type'] ?? null,
                'category_id' => $category_id,
                'created_by' => $uid
            ]);

            // ➕ Create attribute value
            $arrValue = config($attr['value']);

                $values = is_array($arrValue)
                    ? $arrValue
                    : (isset($arrValue) ? [$arrValue] : []);


                $payload = [
                    'item_id' => $item_id,
                    'attribute_id' => $attribute->id,
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
        }

        return response()->json([
            'message' => 'Attributes processed successfully!',
            'status' => 200,
            'data' => $attr_detail,
        ], 200);
    }


    public function show(string $id)
    {
        $attribute = Attribute::find($id);

        if (!$attribute) {
            return response()->json([
                'message' => 'Attribute not found!',
            ], 404);
        }

        return response()->json([
            'message' => 'Attribute retrieved successfully!',
            'status' => 200,
            'data' => $attribute,
        ], 200);
    }


    public function atrrByItem($id){


          $result = $this->attributeService
            ->transformAttributes($id);

        return response()->json([
            'status'=>200,
            'message'=>'Attribute selected successfully.',
            'data'=> $result,
        ],201);
    }


    public function getAttrUnit(Request $request)
    {

        $attrs = $this->attributeService
            ->attrUnit($request);


        return response()->json([
            'status'  => 200,
            'message' => 'Attribute selected successfully.',
            'data'=> $attrs,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $attribute = Attribute::find($id);

        if (!$attribute) {
            return response()->json([
                'message' => 'Attribute not found!',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer',
        ]);

        $attribute->update($validated);

        return response()->json([
            'message' => 'Attribute updated successfully',
            'status' => 200,
            'data' => $attribute,
        ], 200);
    }

    public function destroy(string $id)
    {
        $attribute = Attribute::find($id);
        if (!$attribute) {
            return response()->json([
                'message' => 'Attribute not found!',
            ], 404);
        }

        $attribute->delete();
        return response()->json([
            'message' => 'Attribute deleted successfully',
            'status' => 200,
            'data' => $attribute,
        ], 200);
    }
}

