<?php
namespace App\Services;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Services\AttributeService;
use App\Services\DetailService;


class ItemService {
    protected $attributeService;
    protected $detailService;
    public function __construct(AttributeService $attributeService, DetailService $detailService) {
        $this->attributeService = $attributeService;
        $this->detailService = $detailService;
    }
    // ItemService methods would go here
    public function getImage($itemId) {
        // Logic to show item image
        $images = DB::table('item_images')
            ->join('images', 'images.id', '=', 'item_images.image_id')
            ->where('item_images.item_id', $itemId)
            ->select('item_images.item_id', 'images.id as image_id', 'images.image')
            ->orderBy('item_images.item_id')
            ->orderBy('images.id')
            ->get();

        $imageList = [];
        foreach ($images as $image) {
            $imageUrl = url('storage/images/' . $image->image);
            $imageList[] = [
                'image_id' => $image->image_id,
                'image' => $imageUrl,
            ];
        }
        return $imageList;
    }


    public function getItem($id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;

        $rows = DB::table('items')
            ->leftJoin('categories', 'categories.category_id', '=', 'items.category_id')
            ->leftJoin('attribute_details', 'attribute_details.item_id', '=', 'items.item_id')
            ->leftJoin('attributes', 'attributes.id', '=', 'attribute_details.attribute_id')
            ->leftJoin('brands', 'brands.brand_id', '=', 'items.brand_id')
            ->leftJoin('scales', 'scales.scale_id', '=', 'items.scale_id')
            ->leftJoin('users', 'users.id', '=', 'items.created_by')
            ->leftJoin('item_images', 'item_images.item_id', '=', 'items.item_id')
            ->leftJoin('images', 'images.id', '=', 'item_images.image_id')
            ->leftJoin('profiles', 'users.profile_id', '=', 'profiles.id')
            ->where('profiles.id', $proId)
            ->where('items.item_id', $id)
            ->where('items.is_deleted', 0)
            ->select(
                'items.*',
                'scales.scale_id',
                'scales.scale_name',
                'brands.brand_id',
                'brands.brand_name',
                'categories.category_id',
                'categories.category_name',
                'attributes.name as attribute_name',
                'attributes.type as attribute_type',
                'images.id as image_id',
                'images.image as img_path',
                DB::raw('items.item_price - (items.item_price * (items.discount / 100)) as price_discount'),
                DB::raw('items.wholesale_price - (items.wholesale_price * (items.discount / 100)) as wholesale_price_discount')
            )
            ->get();

        if ($rows->count() == 0) {
            return response()->json([
                "message" => "Item not found",
                "status" => 404,
                "data" => null
            ]);
        }

        $item = $rows->first();
        $imagelist = $this->getImage($id);
        // ---- Build Attributes ---- //
        $attributes = $this->attributeService->transformAttributes($id);
        $stock = $this->detailService->quanItems($id)[0];

        $data = [
            "id" => $item->item_id,
            "barcode" => $item->barcode,
            "code" => $item->item_code,
            "name" => $item->item_name,
            "price" => (float)$item->item_price,
            "cost" => (float)$item->item_cost,
            "price_discount" => (float)$item->price_discount,
            "wholesale_price" => (float)$item->wholesale_price,
            "wholesale_price_discount" => (float)$item->wholesale_price_discount,
            "image" => !empty($imagelist) ? $imagelist[0]['image'] : null,
            "images" => $imagelist,
            "category_id" => $item->category_id,
            "category_name" => $item->category_name,
            "brand_id" => $item->brand_id,
            "brand_name" => $item->brand_name,
            "scale_id" => $item->scale_id,
            "scale_name" => $item->scale_name,
            "rating" => (float)($item->rating ?? 0),
            "reviews" => (int)($item->reviews ?? 0),
            "stock" => $stock,
            "discount" => (int)$item->discount,
            "attributes" => $attributes,
            "created_at" => $item->created_at,
            "updated_at" => $item->updated_at,
            "description" => $item->description ?? null,
            // "is_active" => (bool)$item->is_active
        ];

        return $data;
    }

}
