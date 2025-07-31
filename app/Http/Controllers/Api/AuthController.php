<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->mergeIfMissing(['role' => 'pengusul']);

        if ($request->role !== 'subbag_program') {
            $request->merge(['wilayah_kewenangan' => null]);
        }
        
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', Rule::in(['pengusul', 'subbag_program', 'kabid_krpi', 'kepala_brida'])],
            'wilayah_kewenangan' => ['nullable', 'string', 'max:255', 'required_if:role,subbag_program'],
        ]);

        $user = User::create([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'wilayah_kewenangan' => $validated['wilayah_kewenangan'] ?? null,
        ]);

        return response()->json(['message' => 'Registration successful'], 201);
    }



    public function login(Request $request)
    {
        $credentials = $request->validate([
            // Login menggunakan email lebih umum dan aman daripada full_name
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $user = $request->user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logout berhasil.']);
    }
}
