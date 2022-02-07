<?php

return [

    'auth_redirect_route' => 'dashboard',
    'redirect_route' => 'dashboard',
    'signin_route' => 'signin',
    'inertia' => true,
    'autum_auth_url' =>  env('IDP_URL', 'https://accounts-local.com.br/login'),
];