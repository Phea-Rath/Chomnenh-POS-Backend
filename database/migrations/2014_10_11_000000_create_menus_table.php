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
            $table->string("menu_icon");
            $table->string("menu_path");
            $table->timestamps();
        });
        //menu_type = 0 footer ,1 sidebar, 2 dashbaord
        \App\Models\Menus::insert([
            ["menu_name" => "Home", 'menu_type' => 0, 'menu_icon' => 'HiHome', 'menu_path' => "/dashboard"],
            ["menu_name" => "Prouducts", 'menu_type' => 0, 'menu_icon' => 'BsInboxesFill', 'menu_path' => "/dashboard/list"],
            ["menu_name" => "Orders", 'menu_type' => 0, 'menu_icon' => 'PiShoppingCartBold', 'menu_path' => "/dashboard/orders"],
            ["menu_name" => "Setting", 'menu_type' => 0, 'menu_icon' => 'GrSettingsOption', 'menu_path' => "/dashboard/setting"],
            ["menu_name" => "Category", 'menu_type' => 1, 'menu_icon' => 'MdCategory', 'menu_path' => "/dashboard/category"],
            ["menu_name" => "Quotations", 'menu_type' => 1, 'menu_icon' => 'MdOutlineRequestQuote', 'menu_path' => "/dashboard/quotations"],
            ["menu_name" => "Brand", 'menu_type' => 1, 'menu_icon' => 'AiFillLike', 'menu_path' => "/dashboard/brand"],
            ["menu_name" => "Scale", 'menu_type' => 1, 'menu_icon' => 'FaBalanceScaleLeft', 'menu_path' => "/dashboard/scale"],
            ["menu_name" => "Warehouse", 'menu_type' => 1, 'menu_icon' => 'RiStore3Line', 'menu_path' => "/dashboard/werehouse"],
            ["menu_name" => "Expense Type", 'menu_type' => 1, 'menu_icon' => 'GiMoneyStack', 'menu_path' => "/dashboard/expanse-type"],
            ["menu_name" => "Expenses", 'menu_type' => 1, 'menu_icon' => 'FaMoneyBillTrendUp', 'menu_path' => "/dashboard/expanse"],
            ["menu_name" => "Order Tracking", 'menu_type' => 2, 'menu_icon' => 'CgTrack', 'menu_path' => "/dashboard/order-tracking"],
            ["menu_name" => "Stock In", 'menu_type' => 2, 'menu_icon' => 'AiFillProduct', 'menu_path' => "/dashboard/stock-list"],
            ["menu_name" => "Stock Transfer", 'menu_type' => 2, 'menu_icon' => 'FaTruck', 'menu_path' => "/dashboard/stock-transfer-list"],
            ["menu_name" => "Stock Tracking", 'menu_type' => 2, 'menu_icon' => 'FaListCheck', 'menu_path' => "/dashboard/record-stock"],
            ["menu_name" => "Stock Sale Tracking", 'menu_type' => 2, 'menu_icon' => 'SiPayloadcms', 'menu_path' => "/dashboard/record-stock-sale"],
            ["menu_name" => "Stock Analysis", 'menu_type' => 2, 'menu_icon' => 'BsGraphUpArrow', 'menu_path' => "/dashboard/analyze-stock"],
            ["menu_name" => "E-Menu", 'menu_type' => 2, 'menu_icon' => 'BsQrCodeScan', 'menu_path' => "/dashboard/e-menu"],
            ["menu_name" => "Reports", 'menu_type' => 2, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/report"],
            ["menu_name" => "Purchases", 'menu_type' => 2, 'menu_icon' => 'BiSolidPurchaseTag', 'menu_path' => "/dashboard/purchases"],
            ["menu_name" => "Suppliers", 'menu_type' => 2, 'menu_icon' => 'FaPeopleCarry', 'menu_path' => "/dashboard/suppliers"],
            ["menu_name" => "Customers", 'menu_type' => 2, 'menu_icon' => 'IoIosPeople', 'menu_path' => "/dashboard/customers"],
            ["menu_name" => "Users", 'menu_type' => 3, 'menu_icon' => 'MdManageAccounts', 'menu_path' => "/dashboard/users"],
            ["menu_name" => "Roles", 'menu_type' => 3, 'menu_icon' => 'BsPersonRolodex', 'menu_path' => "/dashboard/roles"],
            ["menu_name" => "Permission", 'menu_type' => 3, 'menu_icon' => 'GiPadlock', 'menu_path' => "/dashboard/permission"],
            ['menu_name' => "Menus", 'menu_type' => 3, 'menu_icon' => 'BsMenuButtonWideFill', 'menu_path' => "/dashboard/menus"],
            ["menu_name" => "Sale Report", 'menu_type' => 4, 'menu_icon' => 'TbReportMoney', 'menu_path' => "/dashboard/report/sales"],
            ["menu_name" => "Product Sale Report", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/report/sales_item"],
            ["menu_name" => "Expense Report", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/expanse/report"],
            ["menu_name" => "Purchase Report by User", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/purchases/report_user"],
            ["menu_name" => "Delivery Service", 'menu_type' => 2, 'menu_icon' => 'FaTruckFast', 'menu_path' => "/dashboard/delivers"],
            ["menu_name" => "Raw Materials", 'menu_type' => 2, 'menu_icon' => 'FaTruckFast', 'menu_path' => "/dashboard/raw-materials"],
            ["menu_name" => "Productions", 'menu_type' => 2, 'menu_icon' => 'FaTruckFast', 'menu_path' => "/dashboard/production"],
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
