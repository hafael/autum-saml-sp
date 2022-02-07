<?php

use Illuminate\Support\Facades\Route;

Route::webhooks('webhook');

Route::group(['middleware' => config('fortify.middleware', ['web'])], function () {

    $middlewares = [];
    if(!empty(config('fortify'))) {
        $middlewares = ['guest:'.config('fortify.guard')];
    }

    Route::post('signin', '\Autum\SAML\Http\Controllers\Auth\SamlController@signin')->middleware($middlewares)->name('signin');
    Route::get('login', '\Autum\SAML\Http\Controllers\Auth\SamlController@login')->middleware($middlewares)->name('login');
    Route::get('logout', '\App\Http\Controllers\Auth\LoginController@logout');

});

