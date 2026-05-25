<?php

use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ColorController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExchangeRateController;
use App\Http\Controllers\ExpanseItemController;
use App\Http\Controllers\ExpanseMasterController;
use App\Http\Controllers\ExpanseTypeController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrderItemController;
use App\Http\Controllers\OrderMasterController;
use App\Http\Controllers\orderPageController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseDetailController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScaleController;
use App\Http\Controllers\SizeController;
use App\Http\Controllers\StockDetailController;
use App\Http\Controllers\StockMasterController;
use App\Http\Controllers\StockTypeController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use App\Http\Controllers\QuotationController;
use App\Http\Controllers\DeliverController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\PaymentController;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;


/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post("/login", [AuthController::class, "login"]);
Route::post("/telegram-login", [AuthController::class, "handleTelegramLogin"]);
Route::post("/new-password", [AuthController::class, "forgotPassword"]);
Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);
Route::get('/get-all-profiles', [ProfileController::class, 'getAll']);
Route::get('sale-item-marketplace', [orderPageController::class, 'saleItemMarketPlace']);
Route::get('item-marketplace/{id}', [ItemController::class, 'show']);
Route::get('/profile-by-id/{id}', [ProfileController::class, 'show']);
Route::get('/test-telegram', [TelegramService::class, 'testTelegram']);
Route::get('/stock-raw/{id}', [StockMasterController::class, 'showRaw']);
Route::get('stock_masters/{id}', [StockMasterController::class, 'show']);
Route::get('order_masters/{id}', [OrderMasterController::class, 'show']);
Route::get('purchase/{id}', [PurchaseController::class, 'show']);
Route::get('production/{id}', [ProductionController::class, 'show']);

Broadcast::routes(['middleware' => ['auth:sanctum']]);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::post('aba-checkout', [PaymentController::class, 'getPaymentLink'])->middleware('auth:sanctum');
    Route::get('aba-callback', [PaymentController::class, 'callback'])->middleware('auth:sanctum');
    Route::get('get-qrcode', [PaymentController::class, 'getQrCode'])->middleware('auth:sanctum');
    Route::post('send-qr-to-telegram', [PaymentController::class, 'sendQrToTelegram'])->middleware('auth:sanctum');
    Route::get('verify-payment/{md5}', [PaymentController::class, 'verifyPayment'])->middleware('auth:sanctum');
    Route::get('e-menu', [MenuController::class, 'getEMenuByUserId'])->middleware('auth:sanctum');
    Route::post('/guest/{phone_number}', [AuthController::class, 'guest'])->middleware('auth:sanctum');
    Route::get('/alert_order_online', [NotificationController::class, 'orderOnline']);
    Route::get('/alert_stock_waste', [NotificationController::class, 'index']);
    Route::get('/provinces', [AddressController::class, 'getProvinces']);
    Route::get('/districts/{provinceId}', [AddressController::class, 'getDistricts']);
    Route::get('/communes/{districtId}', [AddressController::class, 'getCommunes']);
    Route::get('/villages/{communeId}', [AddressController::class, 'getVillages']);
    Route::get('attr_by_item/{id}', [AttributeController::class, 'atrrByItem']);
    Route::post('get_attr_unit', [AttributeController::class, 'getAttrUnit']);
    Route::resource('attributes', AttributeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('attribute_values', AttributeValueController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('/analysis_profit', [ReportController::class, 'AnalysisProfit']);
    Route::post('/analysis_profit_chart', [ReportController::class, 'AnalysisProfitChart']);
    Route::resource('ratings', RatingController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //B
    Route::resource('brands', BrandController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //C
    Route::resource('categorys', CategoryController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('/customers/{id}', [\App\Http\Controllers\CustomerController::class, 'update']);
    Route::post('/customer/image/{id}', [\App\Http\Controllers\CustomerController::class, 'updateImage']);
    Route::resource('customers', \App\Http\Controllers\CustomerController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('/import/customers', [\App\Http\Controllers\CustomerController::class, 'importCustomers']);
    // Route::resource('colors', ColorController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('delivers', DeliverController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('delivers/{id}', [DeliverController::class,'update']);
    //E
    Route::put('exchange_rate/{id}', [ExchangeRateController::class, 'update']);
    Route::get('exchange_rate/{id}', [ExchangeRateController::class, 'show']);
    Route::post('/expense_report', [ReportController::class, 'expenseReport']);
    Route::resource('expense_masters', ExpanseMasterController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('expense_types', ExpanseTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('expense_items', ExpanseItemController::class)->only(['index', 'show']);
    //I
    Route::get('/items_by_code', [ItemController::class, 'showGroupByCode']);
    Route::post('/import-items-by-code/{type}', [ItemController::class, 'filterItemsByCode']);
    Route::post('/import-items-by-code', [ItemController::class, 'filterItemsByCodeNotType']);
    Route::get('/item_by_stock', [orderPageController::class, 'stockByItem']);
    Route::get('/item_in_stock', [orderPageController::class, 'showInStockByItem']);
    Route::get('/delivery_tracking', [orderPageController::class, 'orderDelivery']);
    Route::resource('items', ItemController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('/items/image/{id}', [ItemController::class, 'updateImage']);
    Route::post('/items/{id}', [ItemController::class, 'update']);
    Route::post('/import_items', [ItemController::class, 'importItem']);
    Route::get('/item-list', [ItemController::class, 'indexMobile']);
    Route::get('/item-by-id/{id}', [ItemController::class, 'showMobile']);
    Route::put('/cancel_removed_item/{id}', [ItemController::class, 'cancelDel']);
    Route::delete('/deleted/{id}', [ItemController::class, 'deleted']);
    //M
    Route::resource('menus', MenuController::class)->only(['index', 'show', 'store', 'update']);
    Route::get('/menu-sidebar', [MenuController::class, 'getMenuSidebarByCurrentUser']);
    Route::get('/menu-inventories', [MenuController::class, 'getMenuInventoryByCurrentUser']);
    Route::get('/menu-home', [MenuController::class, 'getMenuHomeByCurrentUser']);
    Route::get('/menu-setting', [MenuController::class, 'getMenuSettingByCurrentUser']);
    Route::get('/menu-report', [MenuController::class, 'getMenuReportByCurrentUser']);
    Route::get('/menu-sidebar/{id}', [MenuController::class, 'getMenuSidebarByUserId']);
    Route::get('/menu-inventories/{id}', [MenuController::class, 'getMenuInventoryByUserId']);
    Route::get('/menu-home/{id}', [MenuController::class, 'getMenuHomeByUserId']);
    Route::get('/menu-setting/{id}', [MenuController::class, 'getMenuSettingByUserId']);
    Route::get('/menu-report/{id}', [MenuController::class, 'getMenuReportByUserId']);
    Route::post('/menus/{id}', [MenuController::class, 'update']);
    Route::get('/menusByCurrentUser', [PermissionController::class, 'getPermissionMenuByCurrentUser']);
    Route::get('/menusByUser/{id}', [PermissionController::class, 'getPermissionMenuByUser']);
    Route::get('/menusByCurrentUser', [PermissionController::class, 'getPermissionMenuByCurrentUser']);
    Route::get('/menusByUser/{id}', [PermissionController::class, 'getPermissionMenuByUser']);
    Route::get('/order_persent_montly', [OrderItemController::class, 'monthlyOrderPercentCompare']);
    //O
    Route::resource('order_masters', OrderMasterController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/order_invoices', [OrderMasterController::class, 'OrderInvoices']);
    Route::put('/order_cancel/{id}', [OrderMasterController::class, 'cancel']);
    Route::put('/status_order/{id}/{status}', [OrderMasterController::class, 'statusOrder']);
    Route::put('/edit_delivery_service/{id}/{deliver_id}', [OrderMasterController::class, 'addDeliver']);
    Route::put('/edit_delivery_fee/{id}/{delivery_fee}', [OrderMasterController::class, 'addDeliveryFee']);
    Route::resource('order_items', OrderItemController::class)->only(['index', 'show']);
    Route::put('/order_uncancel/{id}', [OrderMasterController::class, 'uncancel']);
    Route::put('/order_payment/{id}/{payment}', [OrderMasterController::class, 'addPayment']);
    Route::get('/order-list', [OrderMasterController::class, 'indexMobile']);
    Route::get('/order-by-id/{id}', [OrderMasterController::class, 'showMobile']);
    Route::get('/quan_order_by_attr', [OrderItemController::class, 'quantityInOrderByItemId']);
    Route::get('/orders/max-id', [OrderMasterController::class, 'getMaxId']);
    Route::get('/order_transection', [OrderMasterController::class, 'orderTransection']);
    Route::get('/order_by_user/{id}', [OrderMasterController::class, 'orderByUser']);
    //P
    Route::post('/profile/image/{id}', [ProfileController::class, 'updateImage']);
    Route::put('/profile/number_phone/{id}', [ProfileController::class, 'updateNumberPhone']);
    Route::put('/profile/telegram_service/{id}', [ProfileController::class, 'updateTelegramService']);
    Route::post('/profile/qr_code/{id}', [ProfileController::class, 'updateImageQr']);
    Route::put('/profile/name/{id}', [ProfileController::class, 'updateName']);
    Route::put('/profile/address/{id}', [ProfileController::class, 'updateAddress']);
    Route::resource('purchase', PurchaseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/purchase-list', [PurchaseController::class, 'indexMobile']);
    Route::get('/purchase-by-id/{id}', [PurchaseController::class, 'showMobile']);
    Route::get('/purchase-raw-list', [PurchaseController::class, 'indexMobileRaw']);
    Route::get('/purchase-raw-by-id/{id}', [PurchaseController::class, 'showMobileRaw']);
    Route::get('/purchase_raw_list', [PurchaseController::class, 'indexRaw']);
    Route::get('/purchase_raw/{id}', [PurchaseController::class, 'showRaw']);
    Route::post('/purchase_raw', [PurchaseController::class, 'storeRaw']);
    Route::put('/purchase_raw/{id}', [PurchaseController::class, 'updateRaw']);
    Route::delete('/purchase_raw/{id}', [PurchaseController::class, 'destroyRaw']);
    Route::put('/purchase_cancel/{id}', [PurchaseController::class, 'purchaseCancel']);
    Route::put('/purchase_uncancel/{id}', [PurchaseController::class, 'purchaseUncancel']);
    Route::put('/purchase_confirm/{id}', [PurchaseController::class, 'purchaseConfirm']);
    Route::put('/purchase_confirm_raw/{id}', [PurchaseController::class, 'purchaseConfirmRaw']);
    Route::put('/purchase_payment/{id}', [PurchaseController::class, 'purchasePayment']);
    Route::resource('permission', PermissionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('production', ProductionController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::put('/permission-remove/{user_id}', [PermissionController::class, 'destroy']);
    Route::post('/purchase_report', [ReportController::class, 'purchaseReport']);
    Route::post('/purchase_report_item', [ReportController::class, 'purchaseReportByItem']);
    Route::resource('purchase_details', PurchaseDetailController::class)->only(['index', 'show']);
    Route::resource('profiles', ProfileController::class)->only(['index', 'show', 'destroy']);
    Route::put('/confirm_production/{id}', [ProductionController::class, 'confirmStock']);
    Route::post('/production_report', [ReportController::class, 'productionReport']);
    Route::post('/production_report_item', [ReportController::class, 'productionReportByItem']);
    Route::post('/production_report_raw', [ReportController::class, 'productionReportByRaw']);
    Route::post('/ap-report', [ReportController::class, 'reportAP']);
    Route::post('/ar-report', [ReportController::class, 'reportAR']);
    Route::post('/debt-analysis', [ReportController::class, 'debtAnalysis']);

    Route::get('/popular_expense', [ExpanseItemController::class, 'popularExpanse']);
    Route::get('/popular_sales', [OrderItemController::class, 'popularSales']);
    //R
    Route::put('/receive_order/{id}', [OrderMasterController::class, 'receiveOrder']);
    Route::post("/register", [AuthController::class, "register"]);
    Route::resource('roles', \App\Http\Controllers\RoleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('raw_materials', \App\Http\Controllers\RawMaterialController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('/raw_material/{id}', [\App\Http\Controllers\RawMaterialController::class, 'update']);
    Route::post('/raw_material_report', [ReportController::class, 'RawMaterialReport']);
    //S
    Route::get("/stock_card", [DashboardController::class, "showCard"]);
    Route::post('/stock_report', [ReportController::class, 'stockReport']);
    Route::post('/stock_report_raw', [ReportController::class, 'stockReportByRaw']);
    Route::post('/stock_report_item', [ReportController::class, 'stockReportByItem']);
    Route::get("/stock_by_warehouse/{id}", [StockMasterController::class, "stockByWarehouse"]);
    Route::post("/stock_graphic", [DashboardController::class, "showGraphic"]);
    Route::resource('sale-items', orderPageController::class)->only(['index']);
    Route::get('/stock/{id}', [StockMasterController::class, 'getStockByOrderNo']);
    Route::get('/stock_transection', [StockMasterController::class, 'stockTransection']);
    Route::get('/stock_transfer', [StockMasterController::class, 'stockTransfer']);
    Route::get('/stock_transfer_list', [StockMasterController::class, 'stockTransferMobile']);
    Route::get('/stock_tracking', [StockMasterController::class, 'stockTracking']);
    Route::get('/popular_stock', [StockMasterController::class, 'popularStockIn']);
    Route::get('/quan_stock_by_attr', [StockDetailController::class, 'quantityInStockByItemId']);
    Route::post('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::post('/supplier/image/{id}', [SupplierController::class, 'updateImage']);
    Route::get('/stock-raw', [StockMasterController::class, 'indexRaw']);
    Route::get('/stock-list', [StockMasterController::class, 'indexMobile']);
    Route::get('/stock-raw-list', [StockMasterController::class, 'indexRawMobile']);
    Route::get('/stock-by-id/{id}', [StockMasterController::class, 'showMobile']);
    Route::get('/stock-raw-by-id/{id}', [StockMasterController::class, 'showRawMobile']);
    Route::resource('suppliers', SupplierController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('/import/suppliers', [SupplierController::class, 'importSuppliers']);
    //report
    Route::post('/sale_report', [ReportController::class, 'saleReport']);
    Route::post('/sale_report_item', [ReportController::class, 'saleReportByItem']);
    Route::post('/dashboard_filter', [DashboardController::class, 'filterDashboard']);

    //Q
    Route::put('/quotation_approved/{id}',[QuotationController::class, 'approved']);
    Route::apiResource('quotations', QuotationController::class);
    Route::put("/quote_status/{id}/{status}", [QuotationController::class, "updateStatusQuote"]);

    // Route::get("/users",[AuthController::class, "index"]);
    Route::resource('scales', ScaleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('sizes', SizeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('stock_types', StockTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('stock_masters', StockMasterController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/stock_masters_raw', [StockMasterController::class, 'storeRaw']);
    Route::put('/stock_masters_raw/{id}', [StockMasterController::class, 'updateRaw']);
    Route::delete('/stock_masters_raw/{id}', [StockMasterController::class, 'destroyRaw']);
    Route::get('/stock_masters_pagination', [StockMasterController::class, 'indexPagination']);
    Route::get('/stock_sale_pagination', [orderPageController::class, 'salesPagination']);
    Route::get('/stock_details_items', [StockDetailController::class, 'groupByItems']);
    Route::resource('stock_details', StockDetailController::class)->only(['index', 'show']);
    //U
    Route::get("/user_login", [UserController::class, "userLogin"]);
    Route::get("/user_by_profile/{id}", [UserController::class, "showByProId"]);
    Route::put("/disabled_user/{id}", [UserController::class, "disabledUser"]);
    Route::put("/disabled_company/{id}", [UserController::class, "disabledCompany"]);
    Route::put("/enabled_user/{id}", [UserController::class, "enabledUser"]);
    Route::put("/enabled_company/{id}", [UserController::class, "enabledCompany"]);
    Route::get("/users", [UserController::class, "index"]);
    Route::get("/users/{id}", [UserController::class, "show"]);
    Route::delete("/users/{id}", [UserController::class, "destroy"]);
    Route::post("/users/{id}", [UserController::class, "update"]);
    Route::post('/user/image/{id}', [UserController::class, 'updateImage']);
    Route::put('/user/role/{id}', [UserController::class, 'updateRole']);
    Route::put('/user/number_phone/{id}', [UserController::class, 'updateNumberPhone']);
    Route::put('/user/name/{id}', [UserController::class, 'updateName']);
    Route::put('/update_waste/{id}', [NotificationController::class, 'updateWasteItem']);
    //V
    Route::put('/view_order/{id}', [OrderMasterController::class, 'viewOrder']);
    //W
    Route::resource('warehouses', WarehouseController::class)->only(['index', 'show', 'store', 'update', 'destroy']);

    Route::get('/image-base64/{filename}', function ($filename) {

        $path = storage_path('app/public/images/' . $filename);

        if (!File::exists($path)) {
            return response()->json([
                'error' => 'Image not found',
                'path' => $path
                ], 404);
                }

                $type = mime_content_type($path);
                $data = base64_encode(file_get_contents($path));

                return response("data:$type;base64,$data")
                ->header('Content-Type', 'text/plain');
                });

    Route::get('/total-cost/{quan}/{id}', [\App\Http\Controllers\StockDetailController::class, 'TotalCost']);
});
