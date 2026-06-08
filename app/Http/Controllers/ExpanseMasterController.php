<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Schema;
use App\Models\ExpanseItems;
use App\Models\ExpanseMaster;
use App\Services\PostImage;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpanseMasterController extends Controller
{
    protected $postImage;

    public function __construct(PostImage $postImage)
    {
        $this->postImage = $postImage;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
{
    $user = Auth::user();
    $proId = $user->profile_id;

    $limit  = $request->input('limit', 10);
    $page   = $request->input('page', 1);
    $search = $request->input('search'); // 🔍 search keyword

    $exclude = ['is_deleted', 'is_active'];
    $columns = Schema::getColumnListing('expense_masters');
    $selectColumns = array_diff($columns, $exclude);
    $selectColumns = array_map(fn($col) => "em.$col", $selectColumns);

    // Paginated masters
    $masters = DB::table('expense_masters as em')
        ->join('users as u', 'em.created_by', '=', 'u.id')
        ->where('u.profile_id', $proId)
        ->where('em.is_deleted', 0)
        ->select($selectColumns)

        // 🔍 SEARCH FILTER
        ->when($search, function ($query) use ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('em.expense_no', 'like', "%{$search}%")
                  ->orWhere('em.expense_by', 'like', "%{$search}%")
                  ->orWhere('em.expense_supplier', 'like', "%{$search}%");
            });
        })

        ->orderBy('em.expense_id', 'desc')
        ->paginate($limit, ['*'], 'page', $page);

    if ($masters->isEmpty()) {
        return response()->json([
            'message' => 'Expanse masters not found!',
            'status'  => 404,
            'data'    => []
        ]);
    }

    $exclude = ['is_deleted'];
    $columns = Schema::getColumnListing('expense_items');
    $selectColumns = array_diff($columns, $exclude);
    // Load items ONLY for current page masters
    $items = DB::table('expense_items as ei')
        ->join('expense_types as et', 'ei.expense_type_id', '=', 'et.expense_type_id')
        ->whereIn('ei.expense_id', collect($masters->items())->pluck('expense_id'))
        ->get()
        ->groupBy('expense_id');

    // Load images ONLY for current page masters
    $images = DB::table('expense_images as exi')
        ->join('images as i', 'exi.image_id', '=', 'i.id')
        ->whereIn('exi.expense_id', collect($masters->items())->pluck('expense_id'))
        ->select('exi.expense_id', 'i.id as image_id', 'i.image')
        ->get()
        ->map(function ($img) {
            $img->image = url('storage/images/' . $img->image);
            return $img;
        })
        ->groupBy('expense_id');

    // Attach items and images to each master
    $result = collect($masters->items())->map(function ($master) use ($items, $images) {
        $master->items = $items->get($master->expense_id) ?? [];
        $master->images = $images->get($master->expense_id) ?? [];
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
        $expense_no = 'EXP-' . str_pad((ExpanseMaster::max('expense_id') + 1), 5, '0', STR_PAD_LEFT);
        $validate = $request->validate([
            'expense_date' => 'required|date',
            'expense_by' => 'required|string|max:255',
            'amount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'expense_other' => 'nullable|string|max:500',
            'expense_supplier' => 'required|string|max:500',
            'items' => 'required|json',
            'purchased_by' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ]);

        $items = json_decode($validate['items'], true);

        $expense_masters = ExpanseMaster::create([
            'expense_no' => $expense_no,
            'expense_date' => $validate['expense_date'],
            'expense_by' => $validate['expense_by'],
            'purchased_by' => $validate['purchased_by'],
            'amount' => $validate['amount'],
            'created_by' => $uid,
            'expense_other' => $validate['expense_other'],
            'expense_supplier' => $validate['expense_supplier'],
        ]);

        if ($request->hasFile('images')) {
            $this->postImage->attachExpenseImages($expense_masters->expense_id, $request->file('images'));
        }

        foreach ($items as $item) {
            ExpanseItems::create([
                'expense_id' => $expense_masters->expense_id,
                'expense_type_id' => $item['expense_type_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total']
            ]);
        }

        // return $this->show($expense_masters->expense_id);
        return response()->json([
            'message' => 'expense created successfully!',
            'status' => 200,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = Auth::user();
        $proId = $user->profile_id;
        $masters = DB::table('expense_masters as em')
            ->join('users as u', 'em.created_by', '=', 'u.id')
            ->where('em.expense_id', $id)
            ->where('em.is_deleted', 0)
            ->where('u.profile_id', $proId)
            ->where('em.is_active', 1)
            ->select('em.*')
            ->get();

            if ($masters->isEmpty()) {
            return response()->json([
                'message' => 'expense master not found!',
                'status' => 404,
                'data' => []
            ]);
        }
        $items = DB::table('expense_items')
            ->join('expense_types', 'expense_items.expense_type_id', '=', 'expense_types.expense_type_id')
            ->whereIn('expense_id', $masters->pluck('expense_id'))
            ->get()
            ->groupBy('expense_id');

        $images = DB::table('expense_images as exi')
            ->join('images as i', 'exi.image_id', '=', 'i.id')
            ->whereIn('exi.expense_id', $masters->pluck('expense_id'))
            ->select('exi.expense_id', 'i.id as image_id', 'i.image')
            ->get()
            ->map(function ($img) {
                $img->image = url('storage/images/' . $img->image);
                return $img;
            })
            ->groupBy('expense_id');

        // Attach items to each master
        $result = $masters->map(function ($master) use ($items, $images) {
            $master->items = $items->get($master->expense_id) ?? [];
            $master->images = $images->get($master->expense_id) ?? [];
            return $master;
        });

        return response()->json([
            'message' => 'expense masters fetched successfully!',
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
        $expense_masters = ExpanseMaster::find($id);
        if (!$expense_masters) {
            return response()->json([
                'message' => 'expense master not found!',
                'status' => 404,
            ]);
        }
        $validate = $request->validate([
            'expense_date' => 'required|date',
            'expense_by' => 'required|string|max:255',
            'purchased_by' => 'nullable|string|max:255',
            'amount' => 'required|numeric|regex:/^\d{1,8}(\.\d{1,2})?$/',
            'expense_other' => 'nullable|string|max:500',
            'expense_supplier' => 'required|string|max:500',
            'items' => 'required|json',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'image_ids' => 'nullable|array',
            'image_ids.*' => 'nullable|integer',
        ]);

        $items = json_decode($validate['items'], true);

        $expense_masters->update([
            'expense_date' => $validate['expense_date'],
            'expense_by' => $validate['expense_by'],
            'purchased_by' => $validate['purchased_by'],
            'amount' => $validate['amount'],
            'expense_other' => $validate['expense_other'],
            'expense_supplier' => $validate['expense_supplier'],
        ]);

        if ($request->hasFile('images')) {
            $this->postImage->replaceExpenseImages($request->image_ids, $id, $request->file('images'));
        }

        ExpanseItems::where('expense_id', $id)->delete();

        foreach ($items as $item) {
            ExpanseItems::create([
                'expense_id' => $id,
                'expense_type_id' => $item['expense_type_id'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'sub_total' => $item['sub_total']
            ]);
        }

        return response()->json([
            'message' => 'expense updated successfully!',
            'status' => 200,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $expense_masters = ExpanseMaster::find($id);
        if (!$expense_masters) {
            return response()->json([
                'message' => 'expense master not found!',
                'status' => 404,
            ]);
        }
        $expense_masters->update([
            'is_deleted' => 1,
        ]);
        $expense_items = ExpanseItems::where('expense_id', $id)->get();
        if ($expense_items) {
            foreach ($expense_items as $item) {
                $item->update([
                    'is_deleted' => 1,
                ]);
            }
        }
        return response()->json([
            'message' => 'expense master deleted successfully!',
            'status' => 200,
        ]);
    }
}
