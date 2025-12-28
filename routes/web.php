<?php

use App\Events\OnlineEvent;
use App\Events\PrivateChannelEvent;
use App\Events\PublicChannelEvent;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/checkup', function () {
    event(new OnlineEvent("online", 1));
    event(new PrivateChannelEvent("private", 1));
    event(new PublicChannelEvent("public come on"));
    return " all event have been sent";
});
