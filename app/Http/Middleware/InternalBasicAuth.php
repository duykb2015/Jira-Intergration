<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) config('services.internal_admin.username');
        $expectedPassword = (string) config('services.internal_admin.password');
        $valid = $expectedUser !== '' && $expectedPassword !== ''
            && hash_equals($expectedUser, (string) $request->getUser())
            && hash_equals($expectedPassword, (string) $request->getPassword());

        if (! $valid) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Clockify Admin"']);
        }

        return $next($request);
    }
}
