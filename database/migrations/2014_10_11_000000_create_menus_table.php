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
            $table->increments('menu_id');
            $table->string('menu_name');
            $table->string('menu_type');
            $table->string('menu_icon')->nullable();
            $table->integer('parent_menu')->nullable();
            $table->integer('order_menu');
            $table->string('menu_path');
            $table->timestamps();
        });

        // menu_type = 0 footer, 1 sidebar, 2 dashboard
        Menus::insert([
            // setting
            ['menu_name' => 'Users', 'menu_type' => 3, 'menu_icon' => '', 'parent_menu' => 8, 'order_menu' => 1, 'menu_path' => '/setting/users'],
            ['menu_name' => 'Roles', 'menu_type' => 3, 'menu_icon' => '', 'parent_menu' => 8, 'order_menu' => 2, 'menu_path' => '/setting/roles'],
            ['menu_name' => 'Permission', 'menu_type' => 3, 'menu_icon' => '', 'parent_menu' => 8, 'order_menu' => 3, 'menu_path' => '/setting/permission'],
            ['menu_name' => 'Menus', 'menu_type' => 0, 'menu_icon' => '', 'parent_menu' => 8, 'order_menu' => 4, 'menu_path' => '/setting/menus'],

            // sidebar
            ['menu_name' => 'Home', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 1, 'menu_path' => '/home'],
            ['menu_name' => 'Prouducts', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 2, 'menu_path' => '/list'],
            ['menu_name' => 'Orders', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 3, 'menu_path' => '/order-list'],
            ['menu_name' => 'Setting', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 4, 'menu_path' => '/setting'],
            ['menu_name' => 'Category', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 5, 'menu_path' => '/category'],
            ['menu_name' => 'Brand', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 6, 'menu_path' => '/brand'],
            ['menu_name' => 'Scale', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 7, 'menu_path' => '/scale'],
            ['menu_name' => 'Warehouse', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 8, 'menu_path' => '/werehouse'],
            ['menu_name' => 'Expense Type', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 9, 'menu_path' => '/expense-type'],
            ['menu_name' => 'Suppliers', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 10, 'menu_path' => '/suppliers'],
            ['menu_name' => 'Customers', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 11, 'menu_path' => '/customers'],
            ['menu_name' => 'Raw Materials', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 12, 'menu_path' => '/raw-materials'],
            ['menu_name' => 'Delivery Service', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 13, 'menu_path' => '/delivers'],
            ['menu_name' => 'Reports', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 14, 'menu_path' => '/report'],
            ['menu_name' => 'Dashboard', 'menu_type' => 1, 'menu_icon' => '', 'parent_menu' => null, 'order_menu' => 0, 'menu_path' => '/dashboard'],

            // home
            ['menu_name' => 'Quotations', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 1, 'menu_path' => '/home/quotations'],
            ['menu_name' => 'Expenses', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 2, 'menu_path' => '/home/expenses'],
            ['menu_name' => 'Order Tracking', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 3, 'menu_path' => '/home/order-tracking'],
            ['menu_name' => 'Productions', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 4, 'menu_path' => '/home/production'],
            ['menu_name' => 'Stock In', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 5, 'menu_path' => '/home/stock-list'],
            ['menu_name' => 'Stock Transfer', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 6, 'menu_path' => '/home/stock-transfer-list'],
            ['menu_name' => 'Stock Tracking', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 7, 'menu_path' => '/home/record-stock'],
            ['menu_name' => 'Stock Sale Tracking', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 8, 'menu_path' => '/home/record-stock-sale'],
            ['menu_name' => 'Stock Analysis', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 9, 'menu_path' => '/home/analyze-stock'],
            ['menu_name' => 'E-Menu', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 10, 'menu_path' => '/home/e-menu'],
            ['menu_name' => 'Purchases', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 11, 'menu_path' => '/home/purchases'],
            ['menu_name' => 'Purchase Raw Material', 'menu_type' => 2, 'menu_icon' => '', 'parent_menu' => 5, 'order_menu' => 12, 'menu_path' => '/home/purchase-raw'],

            // report
            ['menu_name' => 'Sale Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 1, 'menu_path' => '/report/sales'],
            ['menu_name' => 'Product Sale Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 2, 'menu_path' => '/report/sales_item'],
            ['menu_name' => 'Expense Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 3, 'menu_path' => '/report/expenses'],
            ['menu_name' => 'Purchase Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 4, 'menu_path' => '/report/purchases'],
            ['menu_name' => 'Purchase Report by Item', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 5, 'menu_path' => '/report/purchase-item'],
            ['menu_name' => 'Raw Material Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 6, 'menu_path' => '/report/raw-materials'],
            ['menu_name' => 'Analysis Profit', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 7, 'menu_path' => '/report/analysis-profit'],
            ['menu_name' => 'Production Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 8, 'menu_path' => '/report/production'],
            ['menu_name' => 'Production By Raw', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 9, 'menu_path' => '/report/production-raw'],
            ['menu_name' => 'Stock Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 10, 'menu_path' => '/report/stocks'],
            ['menu_name' => 'Stock By Item Report', 'menu_type' => 4, 'menu_icon' => '', 'parent_menu' => 29, 'order_menu' => 11, 'menu_path' => '/report/stock-by-item'],
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
