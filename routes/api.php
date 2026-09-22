<?php

/** @var \Laravel\Lumen\Routing\Router $router */

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->get('health', function () {
        return response()->json(['status' => 'ok']);
    });
});
