<?php

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
    Route::get('/', 'ProductController@index')->name('products.index');
    Route::get('/stockouts', 'ProductController@stockouts')->name('products.stockouts');
    Route::get('/sales', 'ProductController@sales')->name('products.sales');
    Route::get('/limited-offers', 'ProductController@limitedOffers')->name('products.limited-offers');
    Route::get('/multi-buys', 'ProductController@multiBuys')->name('products.multi-buys');
    Route::get('/news', 'ProductController@news')->name('products.news');
    Route::get('/go', 'ProductController@go')->name('products.go');
    Route::get('/{product}', 'ProductController@show')->name('products.show');
});

Route::group(['prefix' => 'search'], function () {
    Route::get('/', 'SearchController@index');
    Route::get('/{query}', 'SearchController@search')->name('search');
});

// Auth::routes();

// Route::get('/home', 'HomeController@index')->name('home');
