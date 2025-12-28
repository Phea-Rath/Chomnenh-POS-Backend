<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    public function getProvinces()
    {
        // Logic to retrieve and return 
        $provinces = DB::table('addresses')->where("type","ខេត្ត")->get();
        return response()->json([
            'message' => 'Brand show successfully!',
            'status' => 200,
            'data' => $provinces,
        ], 200);
    }

    public function getDistricts($provinceId)
    {
        // Logic to retrieve and return districts based on province 
        $districts = DB::table('addresses')->where("sub_of",$provinceId)->get();
        return response()->json([
            'message' => 'Brand show successfully!',
            'status' => 200,
            'data' => $districts,
        ], 200);
    }
    public function getCommunes($districtId)
    {
        // Logic to retrieve and return communes based on district ID
        $communes = DB::table('addresses')->where("sub_of",$districtId)->get();
        return response()->json([
            'message' => 'Brand show successfully!',
            'status' => 200,
            'data' => $communes,
        ], 200);
    }
    public function getVillages($communeId)
    {
        // Logic to retrieve and return villages based on commune ID
        $villages = DB::table('addresses')->where("sub_of",$communeId)->get();
        return response()->json([
            'message' => 'Brand show successfully!',
            'status' => 200,
            'data' => $villages,
        ], 200);
    }
}
