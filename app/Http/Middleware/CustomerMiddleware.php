<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
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
            if ($role === 'customer' || (is_object($role) && $role->customer)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthorized. Customer access required.'], 403);
    }
}
