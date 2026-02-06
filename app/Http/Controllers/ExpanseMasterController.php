<?php

namespace App\Http\Controllers;

use App\Models\ExpanseItems;
use App\Models\ExpanseMaster;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpanseMasterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $user = Auth::user();
    $uid  = $user->id;

    $limit  = $request->input('limit', 10);
    $page   = $request->input('page', 1);
    $search = $request->input('search'); // 🔍 search keyword

    // Paginated masters
    $masters = DB::table('expanse_masters as em')
        ->where('em.created_by', $uid)
        ->where('em.is_deleted', 0)

        // 🔍 SEARCH FILTER
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('em.expanse_code', 'like', "%{$search}%")
                  ->orWhere('em.expanse_name', 'like', "%{$search}%")
                  ->orWhere('em.note', 'like', "%{$search}%");
            });
        })

        ->orderBy('em.expanse_id', 'desc')
        ->paginate($limit, ['*'], 'page', $page);

    if ($masters->isEmpty()) {
        return response()->json([
            'message' => 'Expanse masters not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    // Load items ONLY for current page masters
    $items = DB::table('expanse_items as ei')
        ->join('expanse_types as et', 'ei.expanse_type_id', '=', 'et.expanse_type_id')
        ->whereIn('ei.expanse_id', collect($masters->items())->pluck('expanse_id'))
        ->get()
        ->groupBy('expanse_id');

    // Attach items to each master
    $result = collect($masters->items())->map(function ($master) use ($items) {
        $master->items = $items->get($master->expanse_id) ?? [];
        return $master;
    });

    return response()->json([
        'message' => 'Expanse masters fetched successfully!',
        'status'  => 200,
        'data'    => $result->toArray(),
        'pagination' => [
            'current_page' => $masters->currentPage(),
            'per_page'     => $masters->perPage(),
            'total'        => $masters->total(),
            'last_page'    => $masters->lastPage(),
        ]
    ]);
}



    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $uid = $user->id;
        $expanse_no = 'EXP-' . str_pad((ExpanseMaster::max('expanse_id') + 1), 5, '0', STR_PAD_LEFT);
        $validate = $request->validate([
            // 'expanse_no',
            'expanse_date' => 'required|date',
            'expanse_by' => 'required|string|max:255',
            'amount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            // 'created_by',
            'expanse_other' => 'required|string|max:500',
            'expanse_supplier' => 'required|string|max:500',
            // 'expanse_id',
            'items' => 'required|array|min:1',
            'items.*.expanse_type_id' => 'required|integer',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.sub_total' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/'
        ]);

        $expanse_masters = ExpanseMaster::create([
            'expanse_no' => $expanse_no,
            'expanse_date' => $validate['expanse_date'],
            'expanse_by' => $validate['expanse_by'],
            'amount' => $validate['amount'],
            'created_by' => $uid,
            'expanse_other' => $validate['expanse_other'],
            'expanse_supplier' => $validate['expanse_supplier'],
        ]);

        $expanse_items = [];
        foreach ($validate['items'] as $item) {
            $expanse_items[] = ExpanseItems::create([
                'expanse_id' => ExpanseMaster::max('expanse_id'),
                'expanse_type_id' => $item['expanse_type_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total']
            ]);
        }

        return $this->show($expanse_masters->expanse_id);
        // return response()->json([
        //     'message' => 'expanse created successfully!',
        //     'status' => 200,
        //     'data' => [
        //         'expanse_masters' => $expanse_masters,
        //         'expanse_items' => $expanse_items,
        //     ],
        // ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $uid = $user->id;
        $masters = DB::table('expanse_masters')
            ->where('expanse_id', $id)
            ->where('is_deleted', 0)
            ->where('created_by', $uid)
            ->where('is_active', 1)
            ->get();

        $items = DB::table('expanse_items')
            ->join('expanse_types', 'expanse_items.expanse_type_id', '=', 'expanse_types.expanse_type_id')
            ->whereIn('expanse_id', $masters->pluck('expanse_id'))
            ->get()
            ->groupBy('expanse_id');

        // Attach items to each master
        $result = $masters->map(function ($master) use ($items) {
            $master->items = $items->get($master->expanse_id) ?? [];
            return $master;
        });

        return response()->json([
            'message' => 'expanse masters fetched successfully!',
            'status' => 200,
            'data' => $result[0]
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $expanse_masters = ExpanseMaster::find($id);
        if (!$expanse_masters) {
            return response()->json([
                'message' => 'expanse master not found!',
                'status' => 404,
            ]);
        }
        $validate = $request->validate([
            // 'expanse_no',
            'expanse_date' => 'required|date',
            'expanse_by' => 'required|string|max:255',
            'amount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            // 'created_by',
            'expanse_other' => 'required|string|max:500',
            'expanse_supplier' => 'required|string|max:500',
            // 'expanse_id',
            'items' => 'required|array|min:1',
            'items.*.expanse_type_id' => 'required|integer',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|integer',
            'items.*.unit_price' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'items.*.sub_total' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/'
        ]);

        $expanse_masters->update([
            // 'expanse_no'=>$expanse_no,
            'expanse_date' => $validate['expanse_date'],
            'expanse_by' => $validate['expanse_by'],
            'amount' => $validate['amount'],
            'expanse_other' => $validate['expanse_other'],
            'expanse_supplier' => $validate['expanse_supplier'],
        ]);

        if ($expanse_masters) {
            ExpanseItems::where('expanse_id', $id)->delete();
        }

        $expanse_items = [];
        foreach ($validate['items'] as $item) {
            $expanse_items[] = ExpanseItems::create([
                'expanse_id' => $expanse_masters->expanse_id,
                'expanse_type_id' => $item['expanse_type_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total']
            ]);
        }

        return $this->show($id);

        // return response()->json([
        //     'message' => 'expanse updated successfully!',
        //     'status' => 200,
        //     'data' => [
        //         'expanse_masters' => $expanse_masters,
        //         'expanse_items' => $expanse_items,
        //     ],
        // ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expanse_masters = ExpanseMaster::find($id);
        if (!$expanse_masters) {
            return response()->json([
                'message' => 'expanse master not found!',
                'status' => 404,
            ]);
        }
        $expanse_masters->update([
            'is_delete' => 1,
        ]);
        $expanse_items = ExpanseItems::where('expanse_id', $id)->get();
        if ($expanse_items) {
            foreach ($expanse_items as $item) {
                $item->update([
                    'is_delete' => 1,
                ]);
            }
        }
        return response()->json([
            'message' => 'expanse master deleted successfully!',
            'status' => 200,
            'data' => $expanse_masters
        ]);
    }
}
