<?php

namespace App\Providers;

use App\Models\ApiToken;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Resolve the current user from "Authorization: Bearer <token>".
     * Returns null (=> 401 from the auth middleware) when the header is
     * missing or the token hash is unknown.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['auth']->viaRequest('api', function ($request) {
            $plain = $request->bearerToken();

            if (! $plain) {
                return null;
            }

            $token = ApiToken::with('user')
                ->where('token_hash', ApiToken::hashToken($plain))
                ->first();

            if (! $token) {
                return null;
            }

            // Best-effort audit timestamp; not worth a failed request if it races.
            $token->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();

            // Remember which token authenticated this request so logout can
            // revoke exactly that one (not every device the user is on).
            $request->attributes->set('api_token', $token);

            return $token->user;
        });
    }
}
