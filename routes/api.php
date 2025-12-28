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
Route::post('/send-otp', [OtpController::class, 'sendOtp']);
Route::post('/verify-otp', [OtpController::class, 'verifyOtp']);


Broadcast::routes(['middleware' => ['auth:sanctum']]);
Route::group(['middleware' => ['auth:sanctum']], function () {
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
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
    Route::resource('ratings', RatingController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //B
    Route::resource('brands', BrandController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //C
    Route::resource('categorys', CategoryController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::post('/customers/{id}', [\App\Http\Controllers\CustomerController::class, 'update']);
    Route::resource('customers', \App\Http\Controllers\CustomerController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::resource('colors', ColorController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //E
    Route::get('/expanse_by_week',[DashboardController::class, 'expanseWeek']);
    Route::get('/expanse_by_month',[DashboardController::class, 'expanseMonth']);
    Route::get('/expanse_by_day',[DashboardController::class, 'expanseDay']);
    Route::get('/expanse_by_hour',[DashboardController::class, 'expanseHour']);
    Route::put('exchange_rate/{id}', [ExchangeRateController::class, 'update']);
    Route::get('exchange_rate/{id}', [ExchangeRateController::class, 'show']);
    Route::post('/expanse_report', [ReportController::class, 'expanseReport']);
    Route::resource('expanse_masters', ExpanseMasterController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('expanse_types', ExpanseTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('expanse_items', ExpanseItemController::class)->only(['index', 'show']);
    //I
    Route::get('/items_by_code', [ItemController::class, 'showGroupByCode']);
    Route::get('/item_by_stock', [orderPageController::class, 'indexTransfer']);
    Route::get('/item_in_stock', [orderPageController::class, 'showInStockByItem']);
    Route::resource('items', ItemController::class)->only(['index', 'show', 'store', 'destroy']);
    Route::post('/items/{id}', [ItemController::class, 'update']);
    Route::post('/import_items', [ItemController::class, 'importItem']);
    //M
    Route::resource('menus', MenuController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('/order_persent_montly', [OrderItemController::class, 'monthlyOrderPercentCompare']);
    //O
    Route::resource('order_masters', OrderMasterController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::put('/order_cancel/{id}', [OrderMasterController::class, 'cancel']);
    Route::resource('order_items', OrderItemController::class)->only(['index', 'show']);
    Route::put('/order_uncancel/{id}', [OrderMasterController::class, 'uncancel']);
    Route::get('/quan_order_by_attr', [OrderItemController::class, 'quantityInOrderByItemId']);
    Route::get('/orders/max-id', [OrderMasterController::class, 'getMaxId']);
    Route::get('/order_transection', [OrderMasterController::class, 'orderTransection']);
    //P
    Route::post('/profile/image/{id}', [ProfileController::class, 'updateImage']);
    Route::put('/profile/number_phone/{id}', [ProfileController::class, 'updateNumberPhone']);
    Route::put('/profile/name/{id}', [ProfileController::class, 'updateName']);
    Route::resource('purchase', PurchaseController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::put('/purchase_cancel/{id}', [PurchaseController::class, 'purchaseCancel']);
    Route::put('/purchase_uncancel/{id}', [PurchaseController::class, 'purchaseUncancel']);
    Route::put('/purchase_confirm/{id}', [PurchaseController::class, 'purchaseConfirm']);
    Route::put('/purchase_payment/{id}', [PurchaseController::class, 'purchasePayment']);
    Route::get('/purchase_by_week',[DashboardController::class, 'purchaseByWeek']);
    Route::get('/purchase_by_month',[DashboardController::class, 'purchaseByMonth']);
    Route::get('/purchase_by_day',[DashboardController::class, 'purchaseByDay']);
    Route::get('/purchase_by_hour',[DashboardController::class, 'purchaseByHour']);
    Route::resource('permission', PermissionController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::delete('/permission/{user_id}/{menu_id}', [PermissionController::class, 'destroy']);
    Route::post('/purchase_report', [ReportController::class, 'purchaseReport']);
    Route::post('/purchase_report_user', [ReportController::class, 'purchaseReportByUser']);
    Route::resource('purchase_details', PurchaseDetailController::class)->only(['index', 'show']);
    Route::resource('profiles', ProfileController::class)->only(['index', 'show', 'destroy']);

    Route::get('/popular_expanse', [ExpanseItemController::class, 'popularExpanse']);
    Route::get('/popular_sales', [OrderItemController::class, 'popularSales']);
    //R
    Route::put('/receive_order/{id}', [OrderMasterController::class, 'receiveOrder']);
    Route::post("/register", [AuthController::class, "register"]);
    Route::resource('roles', \App\Http\Controllers\RoleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    //S
    Route::get("/stock_card", [DashboardController::class, "showCard"]);
    Route::get("/stock_by_warehouse/{id}", [StockMasterController::class, "stockByWarehouse"]);
    Route::post("/stock_graphic", [DashboardController::class, "showGraphic"]);
    Route::resource('sale-items', orderPageController::class)->only(['index']);
    Route::get('/stock/{id}', [StockMasterController::class, 'getStockByOrderNo']);
    Route::get('/stock_transection', [StockMasterController::class, 'stockTransection']);
    Route::get('/popular_stock', [StockMasterController::class, 'popularStockIn']);
    Route::get('/quan_stock_by_attr', [StockDetailController::class, 'quantityInStockByItemId']);
    Route::post('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::resource('suppliers', SupplierController::class)->only(['index', 'show', 'store', 'destroy']);
    //report
    Route::post('/sale_report', [ReportController::class, 'saleReport']);
    Route::post('/sale_report_item', [ReportController::class, 'saleReportByItem']);
    Route::get('/sale_by_week',[DashboardController::class, 'saleByWeek']);
    Route::get('/sale_by_month',[DashboardController::class, 'saleByMonth']);
    Route::get('/sale_by_day',[DashboardController::class, 'saleByDay']);
    Route::get('/sale_by_hour',[DashboardController::class, 'saleByHour']);

    // Route::get("/users",[AuthController::class, "index"]);
    Route::resource('scales', ScaleController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('sizes', SizeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::resource('stock_types', StockTypeController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    // Route::get('/items_by_code/{id}', [ItemController::class, 'showGroupByCodeById']);
    Route::resource('stock_masters', StockMasterController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
    Route::get('/stock_masters_pagination', [StockMasterController::class, 'indexPagination']);
    Route::get('/stock_sale_pagination', [orderPageController::class, 'salesPagination']);
    Route::get('/stock_details_items', [StockDetailController::class, 'groupByItems']);
    Route::resource('stock_details', StockDetailController::class)->only(['index', 'show']);
    //U
    Route::get("/user_login", [UserController::class, "userLogin"]);
    Route::get("/users", [UserController::class, "index"]);
    Route::get("/users/{id}", [UserController::class, "show"]);
    Route::delete("/users/{id}", [UserController::class, "destroy"]);
    Route::post("/users/{id}", [UserController::class, "update"]);
    Route::post('/user/image/{id}', [UserController::class, 'updateImage']);
    Route::put('/user/number_phone/{id}', [UserController::class, 'updateNumberPhone']);
    Route::put('/user/name/{id}', [UserController::class, 'updateName']);
    Route::put('/update_waste/{id}', [NotificationController::class, 'updateWasteItem']);
    //V
    Route::put('/view_order/{id}', [OrderMasterController::class, 'viewOrder']);
    //W
    Route::resource('warehouses', WarehouseController::class)->only(['index', 'show', 'store', 'update', 'destroy']);
});
