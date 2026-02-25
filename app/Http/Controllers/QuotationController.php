<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Quotation;
use App\Models\ExchangeRate;
use App\Models\OrderMaster;
use App\Models\OrderItems;
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
    public function index(Request $request)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $limit = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $quotationRows = DB::table('quotations as q')
            ->leftJoin('customers as c', 'q.customer_id', '=', 'c.customer_id')
            ->select('q.*', 'c.customer_name')
            ->where('q.profile_id', $proId)
            ->orderBy('q.created_at', 'desc');
        if ($search) {
            $quotationRows->where(function ($query) use ($search) {
                $query->where('q.quotation_number', 'like', "%{$search}%")
                    ->orWhere('c.customer_name', 'like', "%{$search}%")
                    ->orWhere('q.status', 'like', "%{$search}%");
            });
        }
        $quotationRows = $quotationRows->paginate($limit, ['*'], 'page', $page);

        $quotationIds = $quotationRows->pluck('quotation_id')->toArray();

        $detailRows = DB::table('quotation_details')
            ->whereIn('quotation_id', $quotationIds ?: [0])
            ->get()
            ->groupBy('quotation_id');

        $quotations = $quotationRows->map(function ($q) use ($detailRows) {
            $details = collect($detailRows->get($q->quotation_id, []))->map(function ($d) {
                $detail = (array) $d;
                $img = $this->itemService->getImage($d->item_id)[0]['image'] ?? null;
                $detail['image'] = $img;
                return $detail;
            })->values();

            return [
                ...(array) $q,
                'details' => $details,
            ];
        })->values();

        return response()->json([
            'message' => 'Quotation list',
            'status' => 200,
            'data' => $quotations
        ], 200);
    }

    public function show($id)
    {
        $quotation = DB::table('quotations as q')
            ->leftJoin('customers as c', 'q.customer_id', '=', 'c.customer_id')
            ->select('q.*', 'c.customer_name')
            ->where('q.quotation_id', $id)
            ->first();

        if (!$quotation) {
            return response()->json([
                'message' => 'Quotation not found',
            ], 404);
        }

        $details = DB::table('quotation_details')
            ->where('quotation_id', $id)
            ->get()
            ->map(function ($d) {
                $detail = (array) $d;
                $img = $this->itemService->getImage($d->item_id)[0]['image'] ?? null;
                $detail['image'] = $img;
                return $detail;
            })
            ->values();

        $data = [
            ...(array) $quotation,
            'details' => $details,
        ];

        return response()->json([
            'message' => 'Quotation detail',
            'status' => 200,
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
        $itemCount = DB::table('quotations')->whereBetween('created_at', [$monthStart, $monthEnd])->count() + 1;
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
            // Ã¢Å“â€¦ Save quotation (master)
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

            // Ã¢Å“â€¦ Save quotation details
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

            // Ã¢Å“â€¦ Update quotation (master)
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

            // Ã¢Å“â€¦ Remove old details
            QuotationDetail::where('quotation_id', $quotation->quotation_id)->delete();

            // Ã¢Å“â€¦ Insert new details
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

            // Ã¢Å“â€¦ Delete details first
            QuotationDetail::where('quotation_id', $quotation->quotation_id)->delete();

            // Ã¢Å“â€¦ Delete quotation
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

    public function updateStatusQuote($id, $status){
        $quote = Quotation::find($id);

        if(empty($quote)){
            return response()->json([
                'message' => 'Quotation not found',
                'status' => 404,
            ], 404);
        }

        $quote->status = $status;
        $quote->save();
        if($status == 'approved'){
            $this->approved($id);
        }
        return response()->json([
            'message' => 'Quotation updated status',
            'status' => 200,
        ], 200);
    }


    public function approved($id){
        $user = Auth::user();
        $uid = $user->id;
        $proId = $user->profile_id;
        $now = now();
        $month = $now->format('m');
        $year = $now->format('y');


        $exchange_rate = ExchangeRate::find($proId);
        $orderCount = OrderMaster::where('created_by', $uid)
            ->whereMonth('order_date', $month)
            ->whereYear('order_date', $now->format('Y'))
            ->count();
        $order_no = 'ORD' . $proId . $year . $month . '-' . str_pad($orderCount + 1, 4, '0', STR_PAD_LEFT);
        $order_date = $now->format('Y-m-d');

        $quote = Quotation::with([
                'customer:customer_id,customer_name',
                'details.item:item_id,item_name,item_cost'
            ])->findOrFail($id);

        $order_masters = OrderMaster::create([
            'order_no' => $order_no,
            'order_customer_id' => $quote->customer_id ?? null,
            'sale_type' => 'wholesale' ?? null,
            'online' => 0,
            'status' => 6,
            'order_tel' => null,
            'deliver_id' => 1,
            'order_address' => null,
            'order_date' => now()->format('Y-m-d'),
            'delivery_fee' => $quote->delivery_fee,
            'order_payment_status' => 'paid',
            'order_payment_method' => 'cash',
            'balance' => 0,
            'payment' => $quote->grand_total,
            'order_subtotal' => $quote->order_total,
            'order_discount' => $quote->total_discount,
            'order_tax' => $quote->tax ?? 0,
            'order_total' => $quote->grand_total,
            'created_by' => $uid,
            'order_type' => null,
        ]);

        $order_id = $order_masters->order_id;
        $order_items = [];
        // $order_details = [];

        foreach ($quote->details as $item) {
            $order_items[] = OrderItems::create([
                'order_id' => $order_id,
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'item_price' => $item['price'],
                'discount' => $item['discount'],
                'price' => $item['total_price'],
                'quantity' => $item['quantity'],
                'item_cost' => $item['item']->item_cost ?? 0,
                'item_wholesale_price' => $item['price'] ?? 0,
                'exchange_rate' => (double)$exchange_rate->usd_to_khr,
            ]);
        }

        $quoteUpdate = Quotation::find($id);
        $quoteUpdate->status = 'approved';
        $quoteUpdate->save();

        // return response()->json([
        //     'message' => 'Quotation approved successfull',
        //     'status' => 200,
        //     'data' => $order_masters
        // ], 201);
    }

}
