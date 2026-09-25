<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Closure;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'يجب تسجيل الدخول أولاً.',
            ], 401);
        }

        if ($request->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بالوصول.',
            ], 403);
        }

        return $next($request);
    }
}
