<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'name' => 'required|string|min:2',
            'phone' => 'nullable|string',
            'role' => ['required', Rule::in(['petani', 'pembeli', 'admin_tengkulak', 'tengkulak'])],
            'buyer_type' => ['nullable', Rule::in(['b2b', 'b2c'])],
            'company_name' => 'nullable|string',
            'region_id' => 'nullable|exists:regions,id',
            'address' => 'nullable|string',
        ]);

        $role = $validated['role'] === 'tengkulak' ? 'admin_tengkulak' : $validated['role'];

        if ($role === 'pembeli' && empty($validated['buyer_type'])) {
            return response()->json(['error' => 'buyer_type is required for pembeli'], 422);
        }

        if ($role === 'petani' && empty($validated['region_id'])) {
            return response()->json(['error' => 'region_id is required for petani'], 422);
        }

        // B2C buyers and admin_tengkulak auto-approved; Petani and B2B require KYC approval
        $kycStatus = ($role === 'admin_tengkulak' || ($role === 'pembeli' && ($validated['buyer_type'] ?? '') === 'b2c'))
            ? 'approved'
            : 'unsubmitted';

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'role' => $role,
            'buyer_type' => $validated['buyer_type'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'region_id' => $validated['region_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'kyc_status' => $kycStatus,
            'is_active' => true,
            'token_version' => 0,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'kyc_status' => $user->kyc_status,
                'buyer_type' => $user->buyer_type,
                'company_name' => $user->company_name,
                'region_id' => $user->region_id,
                'avatar_url' => $user->avatar_url,
                'effective_avatar' => $user->effective_avatar,
            ],
            'access_token' => $token,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['error' => 'Invalid email or password'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is deactivated. Please contact support.'], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'role' => $user->role,
                'kyc_status' => $user->kyc_status,
                'buyer_type' => $user->buyer_type,
                'region_id' => $user->region_id,
                'company_name' => $user->company_name,
                'avatar_url' => $user->avatar_url,
                'effective_avatar' => $user->effective_avatar,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json(['ok' => true]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role,
            'buyer_type' => $user->buyer_type,
            'company_name' => $user->company_name,
            'region_id' => $user->region_id,
            'region' => $user->region ? $user->region->name : null,
            'address' => $user->address,
            'kyc_status' => $user->kyc_status,
            'kyc_note' => $user->kyc_note,
            'avatar_url' => $user->avatar_url,
            'effective_avatar' => $user->effective_avatar,
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        // Don't leak user existence
        return response()->json([
            'ok' => true,
            'message' => 'Jika email terdaftar, instruksi pemulihan kata sandi telah dikirim.'
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->token_version += 1;
        $user->save();

        // Revoke all existing tokens
        $user->tokens()->delete();

        return response()->json(['ok' => true, 'message' => 'Kata sandi berhasil diperbarui.']);
    }
}
