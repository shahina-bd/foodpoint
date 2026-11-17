<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RiderMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $role = $request->user()->role;
            if ($role === 'rider' || (is_object($role) && $role->rider)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized. Rider access required.'], 403);
    }
}
