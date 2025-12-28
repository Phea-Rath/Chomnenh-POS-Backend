<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\AttributeService;
use App\Services\DetailService;
use Illuminate\Support\Facades\DB;

class PurchaseDetailController extends Controller
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

    }
    public function show(string $id)
    {


        $purchase_detail = $this->detailService->purchaseDetail($id);
        if(!is_array($purchase_detail)){
            return $purchase_detail->original;
        }
        return response()->json([
            "message" => "Purchase detail get successfully",
            "status" => 200,
            "data" => $purchase_detail,
        ]);
    }
}
