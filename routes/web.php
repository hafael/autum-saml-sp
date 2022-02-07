<?php

use Illuminate\Support\Facades\Route;
use Spatie\WebhookClient\Http\Controllers\WebhookController;

Route::macro('webhooks', function (string $url, string $name = 'default') {
    return Route::post($url, WebhookController::class)->name("webhook-client-{$name}");
});

Route::group(['middleware' => config('fortify.middleware', ['web'])], function () {

    $middlewares = [];
    if(!empty(config('fortify'))) {
        $middlewares = ['guest:'.config('fortify.guard')];
    }

    Route::post('signin', '\Autum\SAML\Http\Controllers\Auth\SamlController@signin')->middleware($middlewares)->name('signin');
    Route::get('login', '\Autum\SAML\Http\Controllers\Auth\SamlController@login')->middleware($middlewares)->name('login');
    Route::get('logout', '\Autum\SAML\Http\Controllers\Auth\SamlController@logout');

});

