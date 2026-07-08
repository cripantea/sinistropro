<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWhatsappServiceSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.whatsapp.secret');
        $provided = $request->header('X-Internal-Secret');

        if (! $secret || $provided !== $secret) {
            abort(401, 'unauthorized');
        }

        return $next($request);
    }
}
