<?php

use App\Models\Menus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->increments("menu_id");
            $table->string("menu_name");
            $table->string("menu_type");
            $table->string("menu_icon")->nullable();
            $table->string("menu_path");
            $table->timestamps();
        });
        //menu_type = 0 footer ,1 sidebar, 2 dashbaord
        \App\Models\Menus::insert([
            //setting
            ["menu_name" => "Users", 'menu_type' => 3, 'menu_icon' => '', 'menu_path' => "/dashboard/users"],
            ["menu_name" => "Roles", 'menu_type' => 3, 'menu_icon' => '', 'menu_path' => "/dashboard/roles"],
            ["menu_name" => "Permission", 'menu_type' => 3, 'menu_icon' => '', 'menu_path' => "/dashboard/permission"],
            ['menu_name' => "Menus", 'menu_type' => 3, 'menu_icon' => '', 'menu_path' => "/dashboard/menus"],
            //SideBar
            ["menu_name" => "Home", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard"],
            ["menu_name" => "Prouducts", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/list"],
            ["menu_name" => "Orders", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/orders"],
            ["menu_name" => "Setting", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/setting"],
            ["menu_name" => "Category", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/category"],
            ["menu_name" => "Brand", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/brand"],
            ["menu_name" => "Scale", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/scale"],
            ["menu_name" => "Warehouse", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/werehouse"],
            ["menu_name" => "Expense Type", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/expanse-type"],
            ["menu_name" => "Suppliers", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/suppliers"],
            ["menu_name" => "Customers", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/customers"],
            ["menu_name" => "Raw Materials", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/raw-materials"],
            ["menu_name" => "Delivery Service", 'menu_type' => 1, 'menu_icon' => '', 'menu_path' => "/dashboard/delivers"],
            //Home
            ["menu_name" => "Quotations", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/quotations"],
            ["menu_name" => "Expenses", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/expanse"],
            ["menu_name" => "Order Tracking", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/order-tracking"],
            ["menu_name" => "Productions", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/production"],
            ["menu_name" => "Stock In", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/stock-list"],
            ["menu_name" => "Stock Transfer", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/stock-transfer-list"],
            ["menu_name" => "Stock Tracking", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/record-stock"],
            ["menu_name" => "Stock Sale Tracking", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/record-stock-sale"],
            ["menu_name" => "Stock Analysis", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/analyze-stock"],
            ["menu_name" => "E-Menu", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/e-menu"],
            ["menu_name" => "Purchases", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/purchases"],
            ["menu_name" => "Reports", 'menu_type' => 2, 'menu_icon' => '', 'menu_path' => "/dashboard/report"],
            //report
            ["menu_name" => "Sale Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/sales"],
            ["menu_name" => "Product Sale Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/sales_item"],
            ["menu_name" => "Expense Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/expenses"],
            ["menu_name" => "Purchase Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/purchases"],
            ["menu_name" => "Purchase Report by Item", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/purchase-item"],
            ["menu_name" => "Raw Material Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/raw-materials"],
            ["menu_name" => "Analysis Profit", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/analysis-profit"],
            ["menu_name" => "Production Report", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/production"],
            ["menu_name" => "Production By Raw", 'menu_type' => 4, 'menu_icon' => '', 'menu_path' => "/dashboard/report/production-raw"],

        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
