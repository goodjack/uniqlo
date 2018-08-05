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
    return view('welcome');
});

Route::group(['prefix' => 'products'], function () {
    Route::get('/stockouts', 'ProductController@stockouts');
    Route::get('/sales', 'ProductController@sales');
    Route::get('/limited-offers', 'ProductController@limitedOffers');
    Route::get('/multi-buys', 'ProductController@multiBuys');
    Route::get('/go', 'ProductController@go')->name('go');
});
Route::resource('products', 'ProductController');

Route::group(['prefix' => 'search'], function () {
    Route::get('/', 'SearchController@index');
    Route::get('/{query}', 'SearchController@search')->name('search');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
