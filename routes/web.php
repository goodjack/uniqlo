<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HmallProductController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StyleHintController;
use Illuminate\Support\Facades\Auth;
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

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/2', function () {
    return view('welcome');
})->name('welcome');

Route::group(['prefix' => 'products'], function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::get('/stockouts', [ProductController::class, 'stockouts'])->name('products.stockouts');
    Route::permanentRedirect('/sales', '/lists/sale')->name('products.sales');
    Route::permanentRedirect('/limited-offers', '/lists/limited-offers')->name('products.limited-offers');
    Route::permanentRedirect('/multi-buys', '/lists/multi-buy')->name('products.multi-buys');
    Route::permanentRedirect('/news', '/lists/new')->name('products.news');
    Route::permanentRedirect('/most-reviewed', '/lists/most-reviewed')->name('products.most-reviewed');
    Route::get('/{product}', [ProductController::class, 'show'])->name('products.show');
});

Route::group(['prefix' => 'hmall-products'], function () {
    Route::get('/{uniqlo_product_code}', [HmallProductController::class, 'show'])->name('uniqlo-hmall-products.show');
    Route::get('/{uniqlo_product_code}/style-hints', [StyleHintController::class, 'show'])->name('uniqlo-style-hints.show');
});

Route::group(['prefix' => 'gu-products'], function () {
    Route::get('/{gu_product_code}', [HmallProductController::class, 'show'])->name('gu-hmall-products.show');
    Route::get('/{gu_product_code}/style-hints', [StyleHintController::class, 'show'])->name('gu-style-hints.show');
});

// 搜尋會打到資料庫做全表掃描，限流避免被當成免費的查詢介面
Route::group(['prefix' => 'search', 'middleware' => 'throttle:30,1'], function () {
    Route::get('/', [SearchController::class, 'index'])->name('search.index');
    Route::get('/keywords', [SearchController::class, 'searchByGoogleCse'])->name('search.google-cse');
    Route::get('/{query}', [SearchController::class, 'show'])->name('search.show');
});

Route::group(['prefix' => 'pages'], function () {
    Route::get('/changelog', [PageController::class, 'getChangelog'])->name('pages.changelog');
    Route::get('/privacy', [PageController::class, 'getPrivacyPolicy'])->name('pages.privacy-policy');
});

Route::group(['prefix' => 'favorites'], function () {
    Route::get('/', [FavoriteController::class, 'index'])->name('favorites.index');
    // 收藏清單在瀏覽器，這支只是拿一串商品編號換卡片，限流避免被當批次查價介面
    Route::post('/cards', [FavoriteController::class, 'cards'])
        ->middleware('throttle:60,1')
        ->name('favorites.cards');
});

Route::group(['prefix' => 'categories'], function () {
    Route::get('/', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/{brand}/{code}', [CategoryController::class, 'show'])
        ->where('brand', 'uniqlo|gu')
        ->name('categories.show');
});

Route::group(['prefix' => 'lists'], function () {
    Route::get('/limited-offers', [ListController::class, 'getLimitedOffers'])->name('lists.limited-offers');
    Route::get('/sale', [ListController::class, 'getSale'])->name('lists.sale');
    Route::get('/most-reviewed', [ListController::class, 'getMostReviewed'])->name('lists.most-reviewed');
    Route::get('/japan-most-reviewed', [ListController::class, 'getJapanMostReviewed'])
        ->name('lists.japan-most-reviewed');
    Route::get('/top-wearing', [ListController::class, 'getTopWearing'])->name('lists.top-wearing');
    Route::get('/new', [ListController::class, 'getNew'])->name('lists.new');
    Route::get('/coming-soon', [ListController::class, 'getComingSoon'])->name('lists.coming-soon');
    Route::get('/multi-buy', [ListController::class, 'getMultiBuy'])->name('lists.multi-buy');
    Route::get('/online-special', [ListController::class, 'getOnlineSpecial'])->name('lists.online-special');
    Route::get('/most-visited', [ListController::class, 'getMostVisited'])->name('lists.most-visited');
});

Route::group(['prefix' => 'old-lists'], function () {
    Route::get('/limited-offers', [ProductController::class, 'limitedOffers'])->name('old-lists.limited-offers');
    Route::get('/sales', [ProductController::class, 'sales'])->name('old-lists.sales');
    Route::get('/most-reviewed', [ProductController::class, 'mostReviewed'])->name('old-lists.most-reviewed');
    Route::get('/news', [ProductController::class, 'news'])->name('old-lists.news');
    Route::get('/multi-buys', [ProductController::class, 'multiBuys'])->name('old-lists.multi-buys');
});

// Auth::routes();

// Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
