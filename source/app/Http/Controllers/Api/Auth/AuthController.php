<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function auth(Request $request) {


        $credentials = $request->only([
            'login',
            'password',
            'device_name',
        ]);


        $user = User::where('email', $request->email)->first();
        $check = Hash::check($request->password, $user->password);
        if (!$user || !$check) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect']
            ]);
        }

        $user->tokens()->delete();
        $token = $user->createToken($request->device_name)->plainTextToken;
        return response()->json(
            ['token' => $token]
        );
    }
}
