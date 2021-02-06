<?php

use App\Http\Controllers\PageController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('home');
})->name('home');

Route::group(['prefix' => 'products'], function () {
    Route::get('/', [ProductController::class, 'index'])->name('products.index');
    Route::get('/stockouts', [ProductController::class, 'stockouts'])->name('products.stockouts');
    Route::get('/sales', [ProductController::class, 'sales'])->name('products.sales');
    Route::get('/limited-offers', [ProductController::class, 'limitedOffers'])->name('products.limited-offers');
    Route::get('/multi-buys', [ProductController::class, 'multiBuys'])->name('products.multi-buys');
    Route::get('/news', [ProductController::class, 'news'])->name('products.news');
    Route::get('/go', [ProductController::class, 'go'])->name('products.go');
    Route::get('/most-reviewed', [ProductController::class, 'mostReviewed'])->name('products.most-reviewed');
    Route::get('/{product}', [ProductController::class, 'show'])->name('products.show');
});

Route::group(['prefix' => 'search'], function () {
    Route::get('/', [SearchController::class, 'index']);
    Route::get('/{query}', [SearchController::class, 'search'])->name('search');
});

Route::group(['prefix' => 'pages'], function () {
    Route::get('/changelog', [PageController::class, 'getChangelog'])->name('pages.changelog');
    Route::get('/privacy', [PageController::class, 'getPrivacyPolicy'])->name('pages.privacy-policy');
});

Route::middleware(['auth:sanctum', 'verified'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');
