<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMaster extends Model
{
    protected $primaryKey = "stock_id";
    protected $fillable = ['stock_no', 'stock_type_id', 'order_id', 'from_warehouse', 'warehouse_id', 'stock_date', 'stock_remark', 'stock_created_by', 'id_transfer', 'stock_detail_id'];
}
