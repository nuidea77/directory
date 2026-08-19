<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->hasVerifiedPhone()) {
            return response()->json([
                'message' => 'Энэ үйлдэлд утасны дугаараа баталгаажуулах шаардлагатай.',
                'code' => 'phone_unverified',
            ], 403);
        }

        return $next($request);
    }
}
