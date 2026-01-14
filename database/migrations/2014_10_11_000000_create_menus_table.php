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
            ["menu_name" => "ទំនិញ", 'menu_type' => 0, 'menu_icon' => 'BsInboxesFill', 'menu_path' => "/dashboard/list"],
            ["menu_name" => "Orders", 'menu_type' => 0, 'menu_icon' => 'PiShoppingCartBold', 'menu_path' => "/dashboard/orders"],
            ["menu_name" => "ការកំណត់", 'menu_type' => 0, 'menu_icon' => 'GrSettingsOption', 'menu_path' => "/dashboard/setting"],
            ["menu_name" => "ប្រភេទទំនិញ", 'menu_type' => 1, 'menu_icon' => 'MdCategory', 'menu_path' => "/dashboard/category"],
            ["menu_name" => "សម្រង់តម្លៃ", 'menu_type' => 1, 'menu_icon' => 'IoColorPaletteSharp', 'menu_path' => "/dashboard/quotations"],
            // ["menu_name" => "ទំហំ", 'menu_type' => 1, 'menu_icon' => 'GiResize', 'menu_path' => "/dashboard/size"],
            ["menu_name" => "Brand", 'menu_type' => 1, 'menu_icon' => 'AiFillLike', 'menu_path' => "/dashboard/brand"],
            ["menu_name" => "Scale", 'menu_type' => 1, 'menu_icon' => 'FaBalanceScaleLeft', 'menu_path' => "/dashboard/scale"],
            ["menu_name" => "ឃ្លាំង", 'menu_type' => 1, 'menu_icon' => 'RiStore3Line', 'menu_path' => "/dashboard/werehouse"],
            // ["menu_name" => "Stock Type", 'menu_type' => 1, 'menu_icon' => 'GrDocumentStore', 'menu_path' => "/dashboard/stock-type"],
            ["menu_name" => "ប្រភេទចំណាយ", 'menu_type' => 1, 'menu_icon' => 'GiMoneyStack', 'menu_path' => "/dashboard/expanse-type"],
            ["menu_name" => "ការចំណាយ", 'menu_type' => 1, 'menu_icon' => 'FaMoneyBillTrendUp', 'menu_path' => "/dashboard/expanse"],
            ["menu_name" => "លក់រាយ", 'menu_type' => 2, 'menu_icon' => 'MdShoppingCart', 'menu_path' => "/dashboard/orders"],
            ["menu_name" => "បញ្ចុលស្តុក", 'menu_type' => 2, 'menu_icon' => 'AiFillProduct', 'menu_path' => "/dashboard/stock-list"],
            ["menu_name" => "ផ្ទេរស្តុក", 'menu_type' => 2, 'menu_icon' => 'FaTruck', 'menu_path' => "/dashboard/stock-transfer-list"],
            ["menu_name" => "តាមដានស្តុក", 'menu_type' => 2, 'menu_icon' => 'FaListCheck', 'menu_path' => "/dashboard/record-stock"],
            ["menu_name" => "តាមដានស្តុកលក់", 'menu_type' => 2, 'menu_icon' => 'SiPayloadcms', 'menu_path' => "/dashboard/record-stock-sale"],
            ["menu_name" => "វិភាគ", 'menu_type' => 2, 'menu_icon' => 'BsGraphUpArrow', 'menu_path' => "/dashboard/analyze-stock"],
            // ["menu_name" => "ប្រតិបត្តិការស្តុក", 'menu_type' => 2, 'menu_icon' => 'FaListOl', 'menu_path' => "/dashboard/stock-transition"],
            ["menu_name" => "មីនុយអេឡិចត្រូនិច", 'menu_type' => 2, 'menu_icon' => 'BsQrCodeScan', 'menu_path' => "/dashboard/e-menu"],
            ["menu_name" => "របាយការណ៍", 'menu_type' => 2, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/report"],
            ["menu_name" => "ការបញ្ជាទិញ", 'menu_type' => 2, 'menu_icon' => 'BiSolidPurchaseTag', 'menu_path' => "/dashboard/purchases"],
            // ["menu_name" => "អត្រាប្ដូរប្រាក់", 'menu_type' => 2, 'menu_icon' => 'FcCurrencyExchange', 'menu_path' => "/dashboard/exchange_rate"],
            ["menu_name" => "អ្នកផ្គតផ្គង់", 'menu_type' => 2, 'menu_icon' => 'FaPeopleCarry', 'menu_path' => "/dashboard/suppliers"],
            ["menu_name" => "អតិថិជន", 'menu_type' => 2, 'menu_icon' => 'IoIosPeople', 'menu_path' => "/dashboard/customers"],
            ["menu_name" => "អ្នកប្រើប្រាស់", 'menu_type' => 3, 'menu_icon' => 'MdManageAccounts', 'menu_path' => "/dashboard/users"],
            ["menu_name" => "តួនាទីអ្នកប្រើប្រាស់", 'menu_type' => 3, 'menu_icon' => 'BsPersonRolodex', 'menu_path' => "/dashboard/roles"],
            ["menu_name" => "សិទ្ធិ", 'menu_type' => 3, 'menu_icon' => 'GiPadlock', 'menu_path' => "/dashboard/permission"],
            ['menu_name' => "មីនុយ", 'menu_type' => 3, 'menu_icon' => 'BsMenuButtonWideFill', 'menu_path' => "/dashboard/menus"],
            ["menu_name" => "របាយការណ៍លក់", 'menu_type' => 4, 'menu_icon' => 'TbReportMoney', 'menu_path' => "/dashboard/report/sales"],
            ["menu_name" => "របាយការណ៍ទំនិញលក់", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/report/sales_item"],
            ["menu_name" => "របាយការណ៍ចំណាយ", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/expanse/report"],
            ["menu_name" => "ការបញ្ជាទិញដោយបុគ្គលិក", 'menu_type' => 4, 'menu_icon' => 'TbReportAnalytics', 'menu_path' => "/dashboard/purchases/report_user"],
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
