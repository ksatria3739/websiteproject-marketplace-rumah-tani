<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is disabled'], 403);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'error' => "Forbidden for role '{$user->role}'",
                'required_roles' => $roles
            ], 403);
        }

        return $next($request);
    }
}
