<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Every endpoint is prefixed with /api so the frontend has one base URL
// and the CORS middleware only ever has to reason about API traffic.
$router->group(['prefix' => 'api'], function () use ($router) {
    // Unauthenticated liveness probe used by the frontend/dev checks.
    $router->get('health', function () {
        return response()->json(['status' => 'ok']);
    });

    // Public: these are the only routes reachable without a bearer token.
    $router->post('auth/register', 'AuthController@register');
    $router->post('auth/login', 'AuthController@login');

    // Protected: "auth" middleware resolves the user from the bearer token
    // (see AuthServiceProvider) and returns 401 JSON otherwise.
    $router->group(['middleware' => 'auth'], function () use ($router) {
        $router->post('auth/logout', 'AuthController@logout');
        $router->get('auth/me', 'AuthController@me');

        // Boards: any owner/member may read; rename/delete are owner-only
        // (enforced inside the controller via BoardAccess, not by routing).
        $router->get('boards', 'BoardController@index');
        $router->post('boards', 'BoardController@store');
        $router->get('boards/{id}', 'BoardController@show');
        $router->patch('boards/{id}', 'BoardController@update');
        $router->delete('boards/{id}', 'BoardController@destroy');

        // Sharing: owner adds/removes members by email / user id.
        $router->post('boards/{boardId}/members', 'BoardMemberController@store');
        $router->delete('boards/{boardId}/members/{userId}', 'BoardMemberController@destroy');
    });
});
