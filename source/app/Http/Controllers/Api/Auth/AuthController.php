<?php

namespace App\Http\Controllers\Api\Auth;

use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function auth(AuthRequest $request)
    {


        $apiOrigin = env('UNILAB_API_ORIGIN') === null ? "https://api.unilab.edu.br/api" :  env('UNILAB_API_ORIGIN');

        $dataAPi = ['login' => $request->login, 'senha' => $request->password];
        $response = Http::post($apiOrigin . '/authenticate', $dataAPi);
        $responseJ = json_decode($response->body());
        $userId  = 0;
        if (isset($responseJ->id)) {
            $userId = intval($responseJ->id);
        }
        if ($userId === 0) {
            throw ValidationException::withMessages([
                'login' => trans('auth.failed'),
            ]);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $responseJ->access_token,
        ];
        $response = Http::withHeaders($headers)->get($apiOrigin . '/user', $headers);
        $responseJ2 = json_decode($response->body());


        $response = Http::withHeaders($headers)->get($apiOrigin . '/bond', $headers);
        $responseJ3 = json_decode($response->body());

        $user = User::firstOrNew(['id' => $userId]);
        $user->id = $userId;
        $user->name = $responseJ2->nome;
        $user->email = $responseJ2->email;
        $user->login = $responseJ2->login;
        $user->division_sig = $responseJ3[0]->sigla_unidade;
        $user->division_sig_id = $responseJ3[0]->id_unidade;
        if($user->role == null) {
            $user->role = $responseJ2->id_status_servidor != 1 ? 'disabled' : 'customer';
        }
        $user->password = $request->password;

        $user->save();

        $user->tokens()->delete();
        $token = $user->createToken($request->device_name)->plainTextToken;
        return response()->json(
            ['token' => $token]
        );

    }
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'sucess'
        ]);
    }
    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json(
            ['me' => $user]
        );
    }
}
