<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKyc
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Authentication required'], 401);
        }

        $needsKyc = ($user->role === 'petani') ||
                     ($user->role === 'admin_tengkulak') ||
                     ($user->role === 'pembeli' && $user->buyer_type === 'b2b');

        if ($needsKyc && $user->kyc_status !== 'approved') {
            return response()->json([
                'error' => 'KYC verification required',
                'kyc_status' => $user->kyc_status,
                'kyc_note' => $user->kyc_note,
            ], 403);
        }

        return $next($request);
    }
}
