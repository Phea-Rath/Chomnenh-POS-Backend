<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\DetailService;
use App\Services\ItemService;
class QuotationController extends Controller
{
    protected $detailService;
    protected $itemService;

    public function __construct(DetailService $detailService, ItemService $itemService)
    {
        $this->detailService = $detailService;
        $this->itemService = $itemService;
    }
    public function index()
    {
        $quotations = Quotation::with([
                'customer:customer_id,customer_name',
                'details.item:item_id,item_name'
            ])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($q) {
                return [
                    // ✅ ALL quotation columns
                    ...$q->toArray(),

                    // ✅ flatten customer_name
                    'customer_name' => $q->customer?->customer_name,

                    // ✅ override details with item_name and image included
                    'details' => $q->details->map(function ($d) {
                        $img = $this->itemService->getImage($d->item_id)[0]['image'] ?? null;
                        return array_merge($d->toArray(), ['image' => $img]);
                    }),
                ];
            });

        return response()->json([
            'message' => 'Quotation list',
            'data' => $quotations
        ], 200);
    }


    public function show($id)
    {
        $quotation = Quotation::with([
                'customer:customer_id,customer_name',
            ])
            ->findOrFail($id);

        $data = [
            // ✅ all quotation fields
            ...$quotation->toArray(),

            // ✅ flatten customer_name
            'customer_name' => $quotation->customer?->customer_name,

            // ✅ override details to include item_name
            'details' => $quotation->details->map(function ($d) {
                        $img = $this->itemService->getImage($d->item_id)[0]['image'] ?? null;
                        return array_merge($d->toArray(), ['image' => $img]);
                    }),
        ];

        return response()->json([
            'message' => 'Quotation detail',
            'data' => $data
        ], 200);
    }



    public function store(Request $request)
    {
        $user = Auth::user();


         // Generate barcode
        $currentDate = Carbon::now();
        $year = $currentDate->format('y'); // Last two digits of year (e.g., 25 for 2025)
        $month = $currentDate->format('m'); // Two-digit month (e.g., 09)
        $day = $currentDate->format('d'); // Two-digit day (e.g., 01)
        $profile_id = '01'; // Assuming a fixed profile_id for this example
        $created_by = str_pad($user->id, 2, '0', STR_PAD_LEFT); // Two-digit created_by (e.g., 02)

        // Count items created in the current month for barcode
        $monthStart = $currentDate->startOfMonth()->format('Y-m-d');
        $monthEnd = $currentDate->endOfMonth()->format('Y-m-d');
        $itemCount = Quotation::whereBetween('created_at', [$monthStart, $monthEnd])->count() + 1;
        $itemCountPadded = str_pad($itemCount, 5, '0', STR_PAD_LEFT); // Five-digit item count (e.g., 00001)

        // Construct barcode (e.g., 010225090100001)
        $code = "QT" . $year . $month . $day . $itemCountPadded;

        $request->validate([
            'customer_id' => 'required|integer',
            'date' => 'required|date',
            'credit_term' => 'nullable|integer',
            'date_term' => 'nullable|date',
            'order_total' => 'required|numeric',
            'tax' => 'required|numeric',
            'delivery_fee' => 'nullable|numeric',
            'total_discount' => 'nullable|numeric',
            'grand_total' => 'required|numeric',
            'status' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.price' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            // ✅ Save quotation (master)
            $quotation = Quotation::create([
                'quotation_number' => $code,
                'customer_id' => $request->customer_id,
                'date' => $request->date,
                'credit_term' => $request->credit_term,
                'date_term' => $request->date_term,
                'order_total' => $request->order_total,
                'tax' => $request->tax,
                'delivery_fee' => $request->delivery_fee ?? 0,
                'total_discount' => $request->total_discount ?? 0,
                'grand_total' => $request->grand_total,
                'status' => $request->status,
                'notes' => $request->notes,
                'profile_id' => $user->profile_id,
                'created_by' => auth()->id(),
            ]);

            // ✅ Save quotation details
            foreach ($request->items as $item) {
                $totalPrice =
                    ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                QuotationDetail::create([
                    'quotation_id' => $quotation->quotation_id,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => $totalPrice,
                    'scale' => $item['scale'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Quotation created successfully',
                'status' => 200,
                'data' => $quotation->load('details')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to create quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'customer_id' => 'required|integer',
            'date' => 'required|date',
            'credit_term' => 'nullable|integer',
            'date_term' => 'nullable|date',
            'order_total' => 'required|numeric',
            'tax' => 'required|numeric',
            'delivery_fee' => 'nullable|numeric',
            'total_discount' => 'nullable|numeric',
            'grand_total' => 'required|numeric',
            'status' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric',
            'items.*.price' => 'required|numeric',
        ]);

        DB::beginTransaction();

        try {
            $quotation = Quotation::findOrFail($id);

            // ✅ Update quotation (master)
            $quotation->update([
                'customer_id' => $request->customer_id,
                'date' => $request->date,
                'credit_term' => $request->credit_term,
                'date_term' => $request->date_term,
                'order_total' => $request->order_total,
                'tax' => $request->tax,
                'delivery_fee' => $request->delivery_fee ?? 0,
                'total_discount' => $request->total_discount ?? 0,
                'grand_total' => $request->grand_total,
                'status' => $request->status,
                'notes' => $request->notes,
            ]);

            // ✅ Remove old details
            QuotationDetail::where('quotation_id', $quotation->quotation_id)->delete();

            // ✅ Insert new details
            foreach ($request->items as $item) {
                $totalPrice =
                    ($item['quantity'] * $item['price']) - ($item['discount'] ?? 0);

                QuotationDetail::create([
                    'quotation_id' => $quotation->quotation_id,
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'discount' => $item['discount'] ?? 0,
                    'total_price' => $totalPrice,
                    'scale' => $item['scale'] ?? null,
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Quotation updated successfully',
                'status' => 200,
                'data' => $quotation->load('details')
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        DB::beginTransaction();

        try {
            $quotation = Quotation::findOrFail($id);

            // ✅ Delete details first
            QuotationDetail::where('quotation_id', $quotation->quotation_id)->delete();

            // ✅ Delete quotation
            $quotation->delete();

            DB::commit();

            return response()->json([
                'message' => 'Quotation deleted successfully',
                'status' => 200,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete quotation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
