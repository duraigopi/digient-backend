<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Response;

/**
 * Lets the Next.js dev server (a different origin) call the API.
 * The origin is pinned to FRONTEND_URL rather than "*" so browsers
 * will not send our bearer tokens to arbitrary sites.
 */
class Cors
{
    public function handle($request, Closure $next)
    {
        $headers = [
            'Access-Control-Allow-Origin' => env('FRONTEND_URL', 'http://localhost:3000'),
            'Access-Control-Allow-Methods' => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, Accept',
            'Access-Control-Max-Age' => '86400',
        ];

        // Preflight: answer immediately without touching the router.
        if ($request->isMethod('OPTIONS')) {
            return new Response('', 204, $headers);
        }

        $response = $next($request);

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        return $response;
    }
}
